import { useContext, useEffect, useMemo, useState } from "@wordpress/element";
import { FilterContext } from "../contexts";
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  LineElement,
  PointElement,
  Title,
  Tooltip,
  Legend,
} from "chart.js";
import { Bar, Line } from "react-chartjs-2";

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  LineElement,
  PointElement,
  Title,
  Tooltip,
  Legend,
);

export default function Dashboard() {
  const { filters, setFilters } = useContext(FilterContext);

  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  /* ================= INIT RANGE SAFE ================= */
  useEffect(() => {
    setFilters((prev) => {
      const today = new Date().toISOString().split("T")[0];

      const alreadyCorrect =
        prev.mode === "range" && prev.range?.from && prev.range?.to;

      if (alreadyCorrect) return prev;

      return {
        ...prev,
        mode: "range",
        range: {
          from: today,
          to: today,
          preset: "same_day",
        },
      };
    });
  }, [setFilters]);

  /* ================= FETCH (FIXED DEPENDENCY) ================= */
  useEffect(() => {
    const api = window.WRM_API;
    if (!api?.url) return;

    const controller = new AbortController();

    setLoading(true);
    setError(null);

    const from = filters.range?.from || "";
    const to = filters.range?.to || "";

    const params = new URLSearchParams({
      from,
      to,
    });

    if (filters.entity && filters.entity !== "all") {
      params.append("entity", filters.entity);
    }

    if (filters.site && filters.site !== "all") {
      params.append("site", filters.site);
    }

    fetch(`${api.url}reports/dashboard?${params.toString()}`, {
      headers: { "X-WP-Nonce": api.nonce },
      signal: controller.signal,
    })
      .then((res) => {
        if (!res.ok) throw new Error(`Error: ${res.status}`);
        return res.json();
      })
      .then(setData)
      .catch((err) => {
        if (err.name !== "AbortError") setError(err.message);
      })
      .finally(() => setLoading(false));

    return () => controller.abort();
  }, [filters.range?.from, filters.range?.to, filters.entity, filters.site]);

  /* ================= HELPERS ================= */
  const money = (v) => Math.round(Number(v || 0));
  const num = (v) => Math.round(Number(v || 0) * 100) / 100;

  const kpi = data?.kpi || {};
  const trend = data?.trend || [];
  const hourly = data?.hourly || [];
  const staff = data?.staff || [];
  const sites = data?.sites_data || [];
  const insights = data?.insights || {};

  /* ================= HOURLY FILTER ================= */
  const filteredHourly = useMemo(() => {
    return hourly
      .map((h) => ({ ...h, hour: Number(h.hour) }))
      .filter((h) => h.hour >= 7 && h.hour <= 23)
      .sort((a, b) => a.hour - b.hour);
  }, [hourly]);

  /* ================= CHARTS ================= */
  const trendChart = useMemo(
    () => ({
      labels: trend.map((t) => t.date),
      datasets: [
        {
          label: "Net",
          data: trend.map((t) => num(t.net)),
          borderColor: "#2563eb",
          tension: 0.3,
        },
        {
          label: "Gross",
          data: trend.map((t) => num(t.gross)),
          borderColor: "#16a34a",
          tension: 0.3,
        },
      ],
    }),
    [trend],
  );

  const hourlyChart = useMemo(
    () => ({
      labels: filteredHourly.map((h) => h.hour),
      datasets: [
        {
          label: "Net",
          data: filteredHourly.map((h) => num(h.net)),
          backgroundColor: "#f59e0b",
        },
      ],
    }),
    [filteredHourly],
  );

  /* ================= STATES ================= */

  if (loading) {
    return (
      <div className="sales">
        <div className="header-bar">
          <div className="skeleton" style={{ width: 160, height: 20 }} />
          <div className="export-buttons">
            <div className="skeleton" style={{ width: 110, height: 32 }} />
            <div className="skeleton" style={{ width: 110, height: 32 }} />
          </div>
        </div>

        <div className="table-card">
          <div
            className="skeleton"
            style={{ width: 140, height: 18, marginBottom: 12 }}
          />
					<div className="table-card">
          <table className="table">
            <tbody>
              {Array.from({ length: 5 }).map((_, i) => (
                <tr key={i}>
                  {Array.from({ length: 6 }).map((_, j) => (
                    <td key={j}>
                      <div className="skeleton" style={{ height: 12 }} />
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
					</div>
        </div>
      </div>
    );
  }
  if (error) return <div className="error">{error}</div>;

  return (
    <div className="dashboard">
      {/* ================= KPI ================= */}
      <div className="kpi">
        {[
          ["Orders", kpi.orders],
          ["Gross", `£${money(kpi.gross)}`],
          ["Net", `£${money(kpi.net)}`],
          ["VAT", `£${money(kpi.vat)}`],
          ["Gratuity", `£${money(kpi.gratuity)}`],
        ].map(([label, value]) => (
          <div className="kpi__card" key={label}>
            <h4>{label}</h4>
            <p>{value || 0}</p>
          </div>
        ))}
      </div>

      {/* ================= CHARTS ================= */}
      <div className="grid grid--charts">
        <div className="card">
          <h2>Daily Trend</h2>
          <Line data={trendChart} />
        </div>

        <div className="card">
          <h2>Hourly Net</h2>
          <Bar data={hourlyChart} />
        </div>
      </div>

      {/* ================= TABLES ================= */}
      <div className="grid grid--tables">
        <div className="card">
          <h2>Site Performance</h2>
          <table>
            <thead>
              <tr>
                <th>Site</th>
                <th>Orders</th>
                <th>Net</th>
                <th>Gross</th>
              </tr>
            </thead>
            <tbody>
              {sites.map((s) => (
                <tr key={`${s.site_id}-${s.name}`}>
                  <td>{s.name}</td>
                  <td>{s.orders}</td>
                  <td>£{money(s.net)}</td>
                  <td>£{money(s.gross)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="card">
          <h2>Staff Performance</h2>
          <table>
            <thead>
              <tr>
                <th>Staff</th>
                <th>Orders</th>
                <th>Net</th>
                <th>Gross</th>
              </tr>
            </thead>
            <tbody>
              {staff
                .filter((s) => s.clerk_name?.trim())
                .sort((a, b) => b.net - a.net)
                .slice(0, 7)
                .map((s) => (
                  <tr key={`${s.clerk_id}-${s.clerk_name}`}>
                    <td>{s.clerk_name}</td>
                    <td>{s.orders}</td>
                    <td>£{money(s.net)}</td>
                    <td>£{money(s.gross)}</td>
                  </tr>
                ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* ================= INSIGHTS ================= */}
      <div className="card">
        <h2>Insights</h2>
        <p>
          Highest Gross Day:{" "}
          <strong>{insights.highest_gross_day || "N/A"}</strong>
        </p>
      </div>
    </div>
  );
}

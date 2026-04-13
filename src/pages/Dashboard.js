import { useContext, useEffect, useState } from "@wordpress/element";
import { FilterContext } from "../App";
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
  Legend
);

export default function Dashboard() {
  const { filters } = useContext(FilterContext);

  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const api = window.WRM_API;
    if (!api?.url) return;

    setLoading(true);
    setError(null);

    const params = new URLSearchParams({
      from: filters.from,
      to: filters.to,
    });

    if (filters.entity) params.append("entity", filters.entity);
    if (filters.site) params.append("site", filters.site);

    fetch(`${api.url}reports/dashboard?${params.toString()}`, {
      headers: { "X-WP-Nonce": api.nonce },
    })
      .then(async (res) => {
        if (!res.ok) throw new Error(`Error: ${res.status}`);
        return res.json();
      })
      .then(setData)
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [filters]);

  /* =========================
     HELPERS (FIXED)
  ========================= */

  const money = (v) => Math.round(Number(v || 0));
  const num = (v) => Math.round(Number(v || 0) * 100) / 100;

  /* =========================
     LOADING STATE
  ========================= */

  if (loading) {
    return (
      <div className="wrm-dashboard">

        <div className="wrm-cards">
          {Array.from({ length: 5 }).map((_, i) => (
            <div className="card" key={i}>
              <div className="skeleton" style={{ height: 10, width: "60%" }} />
              <div className="skeleton" style={{ height: 18, width: "40%", marginTop: 8 }} />
            </div>
          ))}
        </div>

        <div className="table-card skeleton-table" />
        <div className="table-card skeleton-table" />
        <div className="table-card skeleton-table" />
        <div className="table-card skeleton-table" />

      </div>
    );
  }

  if (error) return <div className="loading">{error}</div>;
  if (!data) return <div className="loading">No data available.</div>;

  const { kpi, trend, hourly, staff, sites_data, insights } = data;

  const filteredHourly =
    hourly
      ?.map((h) => ({ ...h, hour: Number(h.hour) }))
      .filter((h) => h.hour >= 7 && h.hour <= 23)
      .sort((a, b) => a.hour - b.hour) || [];

  return (
    <div className="wrm-dashboard">

      {/* =========================
          KPI CARDS
      ========================= */}

      <div className="wrm-cards">

        <div className="card">
          <h4>Orders</h4>
          <p>{kpi?.orders || 0}</p>
        </div>

        <div className="card">
          <h4>Gross</h4>
          <p>£{money(kpi?.gross)}</p>
        </div>

        <div className="card">
          <h4>Net</h4>
          <p>£{money(kpi?.net)}</p>
        </div>

        <div className="card">
          <h4>VAT</h4>
          <p>£{money(kpi?.vat)}</p>
        </div>

        <div className="card">
          <h4>Gratuity</h4>
          <p>£{money(kpi?.gratuity)}</p>
        </div>

      </div>

      {/* =========================
          CHARTS
      ========================= */}

      <div className="charts-row">

        <div className="table-card">
          <h2>Daily Trend</h2>

          <Line
            data={{
              labels: trend?.map((t) => t.date),
              datasets: [
                {
                  label: "Net",
                  data: trend?.map((t) => num(t.net)),
                  borderColor: "#2563eb",
                  tension: 0.3,
                },
                {
                  label: "Gross",
                  data: trend?.map((t) => num(t.gross)),
                  borderColor: "#16a34a",
                  tension: 0.3,
                },
              ],
            }}
          />
        </div>

        <div className="table-card">
          <h2>Hourly Net</h2>

          <Bar
            data={{
              labels: filteredHourly.map((h) => h.hour),
              datasets: [
                {
                  label: "Net",
                  data: filteredHourly.map((h) => num(h.net)),
                  backgroundColor: "#f59e0b",
                },
              ],
            }}
          />
        </div>

      </div>

      {/* =========================
          TABLES
      ========================= */}

      <div className="charts-row">

        <div className="table-card">
          <h2>Site Performance</h2>

          <table className="wrm-table">
            <thead>
              <tr>
                <th>Site</th>
                <th>Orders</th>
                <th>Net</th>
                <th>Gross</th>
              </tr>
            </thead>

            <tbody>
              {sites_data?.map((s) => (
                <tr key={s.site_id}>
                  <td>{s.name}</td>
                  <td>{s.orders}</td>
                  <td>£{money(s.net)}</td>
                  <td>£{money(s.gross)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="table-card">
          <h2>Staff Performance</h2>

          <table className="wrm-table">
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
                ?.filter((s) => s.clerk_name?.trim())
                .sort((a, b) => b.net - a.net)
                .slice(0, 7)
                .map((s) => (
                  <tr key={s.clerk_id}>
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

      {/* =========================
          INSIGHTS
      ========================= */}

      <div className="table-card">
        <h2>Insights</h2>
        <p>
          Highest Gross Day: <strong>{insights?.highest_gross_day || "N/A"}</strong>
        </p>
      </div>

    </div>
  );
}

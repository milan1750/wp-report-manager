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
  Legend,
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

    const params = new URLSearchParams({ from: filters.from, to: filters.to });
    if (filters.entity) params.append("entity", filters.entity);
    if (filters.site) params.append("site", filters.site);

    fetch(`${api.url}reports/dashboard?${params.toString()}`, {
      headers: { "X-WP-Nonce": api.nonce },
    })
      .then(async (res) => {
        if (res.status === 403) throw new Error("Access denied (403)");
        if (!res.ok) throw new Error(`Error: ${res.status}`);
        const json = await res.json();
        if (!json || Object.keys(json).length === 0)
          throw new Error("No data available");
        return json;
      })
      .then(setData)
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [filters.from, filters.to, filters.entity, filters.site]);

  const money = (v) => Number(v || 0).toFixed(2);

	if (loading) {
  return (
    <div className="wrm-content">
      {/* KPI Grid Skeleton */}
      <div className="kpi-grid">
        {Array.from({ length: 5 }).map((_, i) => (
          <div className="kpi" key={i}>
            <div className="kpi-title skeleton" />
            <div className="kpi-value skeleton" />
          </div>
        ))}
      </div>

      {/* Charts Skeleton */}
      <div className="tables-row">
        <div className="table-card skeleton-chart" />
        <div className="table-card skeleton-chart" />
      </div>

      {/* Tables Skeleton */}
      <div className="tables-row">
        <div className="table-card skeleton-table" />
        <div className="table-card skeleton-table" />
      </div>

      {/* Insights Skeleton */}
      <div className="table-card skeleton-insights" />
    </div>
  );
}


  if (error) return <div className="loading">{error}</div>;
  if (!data) return <div className="loading">No data available.</div>;

  const { kpi, trend, hourly, staff, sites_data, eat_in, insights } = data;

  // =========================
  // Empty checks for KPI
  // =========================
  const hasKpi = kpi && Object.keys(kpi).length > 0;
  const hasTrend = trend?.length > 0;
  const hasHourly = hourly?.length > 0;
  const hasStaff = staff?.length > 0;
  const hasSites = sites_data?.length > 0;
  const hasInsights = insights && Object.keys(insights).length > 0;

  // =========================
  // Filter hourly 7-23
  // =========================
  const filteredHourly =
    hourly
      ?.map((h) => ({ ...h, hour: Number(h.hour) }))
      .filter((h) => h.hour >= 7 && h.hour <= 23)
      .sort((a, b) => a.hour - b.hour) || [];


  return (
    <div className="wrm-content">
      {/* KPI GRID */}
      {hasKpi ? (
        <div className="kpi-grid">
          <div className="kpi">
            <div className="kpi-title">Orders</div>
            <div className="kpi-value">{kpi.orders}</div>
          </div>
          <div className="kpi">
            <div className="kpi-title">Gross</div>
            <div className="kpi-value">£{money(kpi.gross)}</div>
          </div>
          <div className="kpi">
            <div className="kpi-title">Net</div>
            <div className="kpi-value">£{money(kpi.net)}</div>
          </div>
          <div className="kpi">
            <div className="kpi-title">VAT</div>
            <div className="kpi-value">£{money(kpi.vat)}</div>
          </div>
          <div className="kpi">
            <div className="kpi-title">Gratuity</div>
            <div className="kpi-value">£{money(kpi.gratuity)}</div>
          </div>
        </div>
      ) : (
        <div className="loading">KPI data not available.</div>
      )}

      {/* CHART ROW */}
      <div className="tables-row">
        <div className="table-card">
          <h2>Daily Trend</h2>
          {hasTrend ? (
            <Line
              data={{
                labels: trend.map((t) => t.date),
                datasets: [
                  {
                    label: "Net",
                    data: trend.map((t) => t.net),
                    borderColor: "blue",
                    backgroundColor: "rgba(0,0,255,0.1)",
                    tension: 0.3,
                  },
                  {
                    label: "Gross",
                    data: trend.map((t) => t.gross),
                    borderColor: "green",
                    backgroundColor: "rgba(0,255,0,0.1)",
                    tension: 0.3,
                  },
                ],
              }}
              options={{
                responsive: true,
                plugins: { legend: { position: "top" } },
              }}
            />
          ) : (
            <div className="loading">No trend data available.</div>
          )}
        </div>

        <div className="table-card">
          <h2>Hourly Net</h2>
          {hasHourly ? (
            <Bar
              data={{
                labels: filteredHourly.map((h) => h.hour),
                datasets: [
                  {
                    label: "Net",
                    data: filteredHourly.map((h) => h.net),
                    backgroundColor: "orange",
                  },
                ],
              }}
              options={{
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                  x: { title: { display: true, text: "Hour" } },
                  y: { title: { display: true, text: "Net Amount ($)" } },
                },
              }}
            />
          ) : (
            <div className="loading">No hourly data available.</div>
          )}
        </div>
      </div>

      {/* TABLES */}
      <div className="tables-row">
        <div className="table-card">
          <h2>Site Performance</h2>
          {hasSites ? (
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
                {sites_data.map((s) => (
                  <tr key={s.site_id}>
                    <td>{s.name}</td>
                    <td>{s.orders}</td>
                    <td>£{money(s.net)}</td>
                    <td>£{money(s.gross)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : (
            <div className="loading">No site data available.</div>
          )}
        </div>

        <div className="table-card">
  <h2>Staff Performance</h2>
  {hasStaff ? (
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
          .filter((s) => s.clerk_name && s.clerk_name.trim() !== "") // skip staff with no name
          .sort((a, b) => b.net - a.net) // optional: sort by net descending
          .slice(0, 9) // take top 7
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
  ) : (
    <div className="loading">No staff data available.</div>
  )}
</div>
      </div>

      {/* INSIGHTS */}
      <div className="table-card">
        <h2>Insights</h2>
        {hasInsights ? (
          <p>
            Highest Gross Day: <strong>{insights.highest_gross_day}</strong>
          </p>
        ) : (
          <div className="loading">No insights available.</div>
        )}
      </div>
    </div>
  );
}

import { transactions } from "../data/mock";

function Dashboard() {
  const revenue = transactions.reduce((a, t) => a + t.total, 0);
  const orders = transactions.length;
  const aov = (revenue / orders).toFixed(2);

  const siteRevenue = groupBySum(transactions, "site", "total");
  const clerkRevenue = groupBySum(transactions, "clerk", "total");
  const paymentMix = groupByCount(transactions, "payment");

  const topSite = Object.entries(siteRevenue).sort((a, b) => b[1] - a[1])[0];

  return (
    <div className="wrm-dashboard">

      {/* KPI CARDS */}
      <div className="wrm-kpi-grid">
        <KPI title="Revenue" value={`£${revenue}`} />
        <KPI title="Orders" value={orders} />
        <KPI title="AOV" value={`£${aov}`} />
        <KPI title="Top Site" value={topSite?.[0]} />
      </div>

      {/* CHARTS */}
      <div className="wrm-grid-2">

        <div className="wrm-card">
          <h3>Revenue by Site</h3>
          <BarChart data={siteRevenue} />
        </div>

        <div className="wrm-card">
          <h3>Revenue by Clerk</h3>
          <BarChart data={clerkRevenue} />
        </div>

        <div className="wrm-card">
          <h3>Payment Methods</h3>
          <PieList data={paymentMix} />
        </div>

      </div>
    </div>
  );
}

/* KPI */
function KPI({ title, value }) {
  return (
    <div className="wrm-kpi">
      <span>{title}</span>
      <strong>{value}</strong>
    </div>
  );
}

/* BAR CHART */
function BarChart({ data }) {
  const max = Math.max(...Object.values(data));

  return (
    <div className="wrm-bar">
      {Object.entries(data).map(([k, v]) => (
        <div key={k} className="wrm-bar-row">
          <span>{k}</span>
          <div className="bar-bg">
            <div style={{ width: (v / max) * 100 + "%" }} />
          </div>
          <span>£{v}</span>
        </div>
      ))}
    </div>
  );
}

/* PIE LIST */
function PieList({ data }) {
  return (
    <ul className="wrm-list">
      {Object.entries(data).map(([k, v]) => (
        <li key={k}>
          {k}: {v}
        </li>
      ))}
    </ul>
  );
}

/* HELPERS */
function groupBySum(arr, key, valueKey) {
  return arr.reduce((acc, item) => {
    acc[item[key]] = (acc[item[key]] || 0) + item[valueKey];
    return acc;
  }, {});
}

function groupByCount(arr, key) {
  return arr.reduce((acc, item) => {
    acc[item[key]] = (acc[item[key]] || 0) + 1;
    return acc;
  }, {});
}

export default Dashboard;

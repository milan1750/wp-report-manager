import { transactions } from "../data/mock";

export default function Insights() {
  const revenue = transactions.reduce((a, b) => a + b.total, 0);
  const avg = revenue / transactions.length;

  return (
    <div className="page">
      <h1>Insights</h1>

      <div className="card">
        <p>Total Revenue: £{revenue}</p>
        <p>Average Order Value: £{avg.toFixed(2)}</p>
        <p>High Performer: {topSite(transactions)}</p>
      </div>
    </div>
  );
}

function topSite(arr) {
  const map = {};
  arr.forEach(t => {
    map[t.site] = (map[t.site] || 0) + t.total;
  });

  return Object.entries(map).sort((a,b)=>b[1]-a[1])[0][0];
}

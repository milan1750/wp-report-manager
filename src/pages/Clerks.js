import { transactions } from "../data/mock";

export default function Clerks() {
  const data = group(transactions, "clerk", "total");

  return (
    <div className="page">
      <h1>Clerk Performance</h1>

      <Bar data={data} />
    </div>
  );
}

const group = (arr, key, val) =>
  arr.reduce((a, b) => {
    a[b[key]] = (a[b[key]] || 0) + b[val];
    return a;
  }, {});

const Bar = ({ data }) => {
  const max = Math.max(...Object.values(data));

  return Object.entries(data).map(([k, v]) => (
    <div className="bar-row" key={k}>
      <span>{k}</span>
      <div className="bar-bg">
        <div style={{ width: (v / max) * 100 + "%" }} />
      </div>
      <span>£{v}</span>
    </div>
  ));
};

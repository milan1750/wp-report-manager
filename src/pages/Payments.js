import { payments } from "../data/mock";

export default function Payments() {
  const map = payments.reduce((acc, p) => {
    acc[p.payment_type] = (acc[p.payment_type] || 0) + p.amount;
    return acc;
  }, {});

  return (
    <div className="page">
      <h1>Payments Breakdown</h1>

      <ul>
        {Object.entries(map).map(([k, v]) => (
          <li key={k}>{k}: £{v}</li>
        ))}
      </ul>
    </div>
  );
}

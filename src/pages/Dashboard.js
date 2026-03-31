import { useContext } from "@wordpress/element";
import { FilterContext } from "../App";
import { transactions } from "../data/mock";

export default function Dashboard() {
  const { filters } = useContext(FilterContext);

  const data = transactions.filter((t) => {
    return (
      t.complete_date >= filters.from &&
      t.complete_date <= filters.to
    );
  });

  const revenue = data.reduce((a, b) => a + b.total, 0);
  const orders = data.length;
  const aov = orders ? revenue / orders : 0;

  return (
    <div>

      <div className="kpi-grid">

        <div className="kpi">
          <span>Revenue</span>
          <strong>£{revenue}</strong>
        </div>

        <div className="kpi">
          <span>Orders</span>
          <strong>{orders}</strong>
        </div>

        <div className="kpi">
          <span>AOV</span>
          <strong>£{aov.toFixed(2)}</strong>
        </div>

      </div>

    </div>
  );
}

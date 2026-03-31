import { useContext } from "@wordpress/element";
import { FilterContext } from "../App";
import { transactions } from "../data/mock";

export default function Transactions() {
  const { filters } = useContext(FilterContext);

  const data = transactions.filter((t) => {
    return (
      t.complete_date >= filters.from &&
      t.complete_date <= filters.to
    );
  });

  return (
    <table className="table">

      <thead>
        <tr>
          <th>ID</th>
          <th>Date</th>
          <th>Site</th>
          <th>Clerk</th>
          <th>Total</th>
        </tr>
      </thead>

      <tbody>
        {data.map((t) => (
          <tr key={t.transaction_id}>
            <td>{t.transaction_id}</td>
            <td>{t.complete_date}</td>
            <td>{t.site_title}</td>
            <td>{t.clerk_name}</td>
            <td>£{t.total}</td>
          </tr>
        ))}
      </tbody>

    </table>
  );
}

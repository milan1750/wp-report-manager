import { useState } from "react";
import { transactions } from "../data/mock";

function Report() {
  const [search, setSearch] = useState("");

  const filtered = transactions.filter(
    (t) =>
      t.site.toLowerCase().includes(search.toLowerCase()) ||
      t.clerk.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="wrm-report">

      {/* FILTER */}
      <div className="wrm-filter">
        <input
          placeholder="Search site, clerk, or payment..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
      </div>

      {/* TABLE */}
      <div className="wrm-table-wrap">
        <table className="wrm-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Date</th>
              <th>Site</th>
              <th>Clerk</th>
              <th>Items</th>
              <th>Total</th>
              <th>Payment</th>
            </tr>
          </thead>

          <tbody>
            {filtered.map((t) => (
              <tr key={t.id}>
                <td>{t.id}</td>
                <td>{t.date}</td>
                <td>{t.site}</td>
                <td>{t.clerk}</td>
                <td>{t.items}</td>
                <td>£{t.total}</td>
                <td>{t.payment}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

    </div>
  );
}

export default Report;

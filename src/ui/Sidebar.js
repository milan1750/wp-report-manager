import { useContext } from "@wordpress/element";
import { PermissionContext } from "../App";

export default function Sidebar({ page, setPage }) {

  const permissions = useContext(PermissionContext);

  const can = (key) => permissions?.[key];

  return (
    <aside className="wrm-sidebar">

      <div className="wrm-brand">
        <h2>WRM</h2>
        <span>Report Manager</span>
      </div>

      <nav className="wrm-nav">

        {can("dashboard") && (
          <button
            className={page === "dashboard" ? "active" : ""}
            onClick={() => setPage("dashboard")}
          >
            📊 Dashboard
          </button>
        )}

        {can("sales") && (
          <button
            className={page === "sales" ? "active" : ""}
            onClick={() => setPage("sales")}
          >
            🧾 Sales
          </button>
        )}

        {can("items") && (
          <button
            className={page === "items" ? "active" : ""}
            onClick={() => setPage("items")}
          >
            🧾 Items
          </button>
        )}

        {can("data") && (
          <button
            className={page === "data" ? "active" : ""}
            onClick={() => setPage("data")}
          >
            🧾 Data
          </button>
        )}

      </nav>

    </aside>
  );
}

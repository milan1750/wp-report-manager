import { useContext } from "@wordpress/element";
import { PermissionContext } from "../contexts";

export default function Sidebar({ page, setPage }) {
  const permissions = useContext(PermissionContext);

  const can = (key) => permissions?.[key];

  return (
    <aside className="sidebar">

      <div className="sidebar__brand">
        <h2>WRM</h2>
        <span>Report Manager</span>
      </div>

      <nav className="nav">

        {can("dashboard") && (
          <button
            className={`nav__button ${page === "dashboard" ? "is-active" : ""}`}
            onClick={() => setPage("dashboard")}
          >
            📊 Dashboard
          </button>
        )}

        {can("sales") && (
          <button
            className={`nav__button ${page === "sales" ? "is-active" : ""}`}
            onClick={() => setPage("sales")}
          >
            🧾 Sales
          </button>
        )}

        {can("daily_sales") && (
          <button
            className={`nav__button ${page === "daily_sales" ? "is-active" : ""}`}
            onClick={() => setPage("daily_sales")}
          >
            🧾 Daily Sales
          </button>
        )}

        {can("items") && (
          <button
            className={`nav__button ${page === "items" ? "is-active" : ""}`}
            onClick={() => setPage("items")}
          >
            🧾 Items
          </button>
        )}

        {can("items_interval") && (
          <button
            className={`nav__button ${page === "items_interval" ? "is-active" : ""}`}
            onClick={() => setPage("items_interval")}
          >
            🧾 Items Interval Sales
          </button>
        )}

        {can("data") && (
          <button
            className={`nav__button ${page === "data" ? "is-active" : ""}`}
            onClick={() => setPage("data")}
          >
            🧾 Data
          </button>
        )}

      </nav>
    </aside>
  );
}

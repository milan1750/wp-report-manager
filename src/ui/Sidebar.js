export default function Sidebar({ page, setPage }) {
  return (
    <aside className="wrm-sidebar">

      <div className="wrm-brand">
        <h2>WRM</h2>
        <span>Report Manager</span>
      </div>

      <nav className="wrm-nav">

        <button
          className={page === "dashboard" ? "active" : ""}
          onClick={() => setPage("dashboard")}
        >
          📊 Dashboard
        </button>

        <button
          className={page === "sales" ? "active" : ""}
          onClick={() => setPage("sales")}
        >
          🧾 Sales
        </button>

        <button
          className={page === "data" ? "active" : ""}
          onClick={() => setPage("data")}
        >
          🧾 Data
        </button>

      </nav>

    </aside>
  );
}

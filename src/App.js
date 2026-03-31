import { useState, createContext } from "@wordpress/element";

import Sidebar from "./ui/Sidebar";
import FilterBar from "./ui/FilterBar";

import Dashboard from "./pages/Dashboard";
import Transactions from "./pages/Transactions";
import Sales from "./pages/Sales";

export const FilterContext = createContext();

export default function App() {
  const [page, setPage] = useState("dashboard");

  const [filters, setFilters] = useState({
    from: "2026-03-20",
    to: "2026-03-23",
    site: "all",
    clerk: "all",
    payment: "all",
  });

  return (
    <FilterContext.Provider value={{ filters, setFilters }}>
      <div className="wrm-layout">

        <Sidebar page={page} setPage={setPage} />

        <div className="wrm-main">

          <FilterBar />

          <div className="wrm-content">
            {page === "dashboard" && <Dashboard />}
            {page === "transactions" && <Transactions />}
            {page === "sales" && <Sales />}
          </div>

        </div>

      </div>
    </FilterContext.Provider>
  );
}

import { useState, useEffect, createContext } from "@wordpress/element";

import FilterBar from "./ui/Filterbar";
import Sidebar from "./ui/Sidebar";

import Dashboard from "./pages/Dashboard";
import Sales from "./pages/Sales";
import Items from "./pages/Items";
import Data from "./pages/Data";

export const PermissionContext = createContext();
export const FilterContext = createContext();

export default function App() {

  const [page, setPage] = useState("dashboard");
  const [permissions, setPermissions] = useState(null);

  const [filters, setFilters] = useState({
    from: "2026-03-20",
    to: "2026-03-23",
    site: "all",
    clerk: "all",
    payment: "all",
  });

  useEffect(() => {

    const api = window.WRM_API;

    fetch(`${api.url}permissions`, {
      headers: { "X-WP-Nonce": api.nonce },
    })
      .then((res) => res.json())
      .then((data) => {

        // data is: { dashboard: true, sales: true, items: true, data: true }
        setPermissions(data);

        // auto default page based on permission
        if (!data.dashboard) {
          if (data.sales) setPage("sales");
          else if (data.items) setPage("items");
          else if (data.data) setPage("data");
        }

      });

  }, []);

  if (!permissions) {
    return <div>Loading permissions...</div>;
  }

  return (
    <PermissionContext.Provider value={permissions}>
      <FilterContext.Provider value={{ filters, setFilters }}>

        <div className="wrm-layout">

          <Sidebar page={page} setPage={setPage} />

          <div className="wrm-main">

            <FilterBar />

            <div className="wrm-content">

              {page === "dashboard" && permissions.dashboard && <Dashboard />}
              {page === "sales" && permissions.sales && <Sales />}
              {page === "items" && permissions.items && <Items />}
              {page === "data" && permissions.data && <Data />}

            </div>

          </div>

        </div>

      </FilterContext.Provider>
    </PermissionContext.Provider>
  );
}

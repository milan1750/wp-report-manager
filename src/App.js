import { useState, useEffect } from "@wordpress/element";

import FilterBar from "./ui/Filterbar";
import Sidebar from "./ui/Sidebar";

import Dashboard from "./pages/Dashboard";
import Sales from "./pages/Sales";
import Items from "./pages/Items";
import Data from "./pages/Data";
import DailySales from "./pages/DailySales";
import ItemsInterval from "./pages/ItemsInterval";

import { normalizeWeekStart } from "./utils/date";

import { PermissionContext, FilterContext } from "./contexts";

import { fetchPermissions, fetchWeeks } from "./services/api";

/* ================= INITIAL FILTER STATE ================= */

const INITIAL_FILTERS = {
  mode: "range",

  range: {
    from: "",
    to: "",
    preset: "",
  },

  compare: {
    base: "",
    base_preset: "same_day",
    compare: "",
    compare_preset: "same_day",
  },

  interval: {
    value: 15,
    unit: "minute",
    preset: "15m",
  },

  site: "all",
  entity: "all",
  payment: "all",
};

export default function App() {
  const [page, setPage] = useState("dashboard");
  const [permissions, setPermissions] = useState(null);
  const [weeksData, setWeeksData] = useState(null);
  const [filters, setFilters] = useState(INITIAL_FILTERS);

  /* ================= BOOTSTRAP ================= */

  useEffect(() => {
    let isMounted = true;

    const init = async () => {
      try {
        const [permData, weekData] = await Promise.all([
          fetchPermissions(),
          fetchWeeks(),
        ]);

        if (!isMounted) return;

        setPermissions(permData);

        const normalizedWeeks = {
          ...weekData,
          week_start: normalizeWeekStart(weekData.week_start),
        };

        setWeeksData(normalizedWeeks);

        const current = normalizedWeeks?.current_week;

        if (current) {
          setFilters((prev) => {
            if (prev.__init) return prev;

            return {
              ...prev,
              __init: true,

              mode: "range",

              range: {
                from: current.start,
                to: current.end,
                preset: current.week || "current_week",
              },
            };
          });
        }

        applyDefaultPage(permData);
      } catch (err) {
        console.error("App init error:", err);
      }
    };

    init();

    return () => {
      isMounted = false;
    };
  }, []);

  /* ================= HELPERS ================= */

  const applyDefaultPage = (perm) => {
    if (perm.dashboard) return;

    if (perm.sales) setPage("sales");
    else if (perm.items) setPage("items");
    else if (perm.data) setPage("data");
    else if (perm.daily_sales) setPage("daily_sales");
    else if (perm.items_interval) setPage("items_interval");
  };

  const applyDefaultFilters = (data) => {
    const current = data?.current_week;
    if (!current) return;

    setFilters((prev) => ({
      ...prev,

      mode: "range",

      range: {
        from: current.start,
        to: current.end,
        preset: current.week || "current_week",
      },

      compare: {
        base: current.end,
        base_preset: "same_day",
        compare: current.end,
        compare_preset: "same_day",
        interval_a_start_time: "00:00",
        interval_a_end_time: "23:59",
      },

      interval: {
        value: 15,
        unit: "minute",
        preset: "15m",
      },
    }));
  };

  /* ================= LOADING STATE ================= */

  if (!permissions || !weeksData) {
    return (
      <div className="wrm-layout">
        <div className="wrm-main">
          <div className="wrm-content">
            <div
              className="skeleton"
              style={{ height: "40px", width: "200px" }}
            />
            <div
              className="skeleton"
              style={{ height: "200px", width: "100%" }}
            />
          </div>
        </div>
      </div>
    );
  }

  /* ================= APP ================= */

  return (
    <PermissionContext.Provider value={permissions}>
      <FilterContext.Provider value={{ filters, setFilters, weeksData }}>
        <div className="app">
          {/* SIDEBAR */}
          <Sidebar page={page} setPage={setPage} />

          {/* MAIN AREA */}
          <div className="main">
            {/* FILTER BAR */}
            <FilterBar />

            {/* PAGE CONTENT */}
            <div className="page">
              {page === "dashboard" && permissions.dashboard && <Dashboard />}
              {page === "sales" && permissions.sales && <Sales />}
              {page === "items" && permissions.items && <Items />}
              {page === "data" && permissions.data && <Data />}
              {page === "daily_sales" && permissions.daily_sales && (
                <DailySales />
              )}
              {page === "items_interval" && permissions.items_interval && (
                <ItemsInterval />
              )}
            </div>
          </div>
        </div>
      </FilterContext.Provider>
    </PermissionContext.Provider>
  );
}

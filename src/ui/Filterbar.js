import { useContext, useEffect, useState } from "@wordpress/element";
import { FilterContext } from "../App";

export default function FilterBar() {
  const { filters, setFilters } = useContext(FilterContext);

  const [weeks, setWeeks] = useState([]);
  const [sites, setSites] = useState([]);
  const [entities, setEntities] = useState([]);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // =========================
  // LOAD DATA
  // =========================
  useEffect(() => {
    const api = window.WRM_API;

    if (!api || !api.url) {
      setError("WRM_API not loaded");
      return;
    }

    // load localized data
    setSites(api.sites || []);
    setEntities(api.entities || []);

    fetch(`${api.url}weeks`, {
      method: "GET",
      headers: {
        "X-WP-Nonce": api.nonce,
      },
    })
      .then((res) => {
        if (!res.ok) throw new Error("Failed to load weeks");
        return res.json();
      })
      .then((data) => {
        const list = data.weeks || [];
        setWeeks(list);

        const current = list.find((w) => w.is_current);

        if (current) {
          setFilters((prev) => ({
            ...prev,
            from: current.start,
            to: current.end,
          }));
        }
      })
      .catch((err) => {
        console.error(err);
        setError(err.message);
      })
      .finally(() => setLoading(false));
  }, []);

	console.log(window.WRM_API);

  // =========================
  // FILTER SITES BY ENTITY
  // =========================
  const filteredSites =
    filters.entity === "all"
      ? sites
      : sites.filter((s) => String(s.entity_id) === String(filters.entity));

  // =========================
  // DATE LOGIC
  // =========================
  const handleDate = (value) => {
    const today = new Date();
    const format = (d) => d.toISOString().split("T")[0];

    let from = "";
    let to = "";

    switch (value) {
      case "today":
        from = to = format(today);
        break;

      case "yesterday":
        const y = new Date(today);
        y.setDate(today.getDate() - 1);
        from = to = format(y);
        break;

      case "this_week":
        const startWeek = new Date(today);
        startWeek.setDate(today.getDate() - today.getDay());
        from = format(startWeek);
        to = format(today);
        break;

      case "last_week":
        const lw = new Date(today);
        lw.setDate(today.getDate() - today.getDay() - 7);

        const le = new Date(today);
        le.setDate(today.getDate() - today.getDay() - 1);

        from = format(lw);
        to = format(le);
        break;

      case "this_month":
        from = format(new Date(today.getFullYear(), today.getMonth(), 1));
        to = format(today);
        break;

      case "this_year":
        from = format(new Date(today.getFullYear(), 0, 1));
        to = format(today);
        break;

      default:
        const week = weeks.find((w) => w.week === value);

        if (week) {
          from = week.start;
          to = week.end;
        }
    }

    setFilters({ ...filters, from, to });
  };

  if (loading) return <div>Loading filters...</div>;
  if (error) return <div style={{ color: "red" }}>{error}</div>;

  return (
    <div className="filter-bar">

      {/* DATE RANGE */}
      <select onChange={(e) => handleDate(e.target.value)}>
        <option value="">Select Range</option>

        <optgroup label="Quick Filters">
          <option value="today">Today</option>
          <option value="yesterday">Yesterday</option>
          <option value="this_week">This Week</option>
          <option value="last_week">Last Week</option>
          <option value="this_month">This Month</option>
          <option value="this_year">This Year</option>
        </optgroup>

        <optgroup label="Weeks">
          {weeks.map((w) => (
            <option key={w.week} value={w.week}>
              {w.week} ({w.start} → {w.end}) {w.is_current ? "🔥" : ""}
            </option>
          ))}
        </optgroup>
      </select>

      {/* DATE PICKERS */}
      <input
        type="date"
        value={filters.from || ""}
        onChange={(e) =>
          setFilters({ ...filters, from: e.target.value })
        }
      />

      <input
        type="date"
        value={filters.to || ""}
        onChange={(e) =>
          setFilters({ ...filters, to: e.target.value })
        }
      />

      {/* ENTITY FILTER */}
      <select
        value={filters.entity || "all"}
        onChange={(e) =>
          setFilters({
            ...filters,
            entity: e.target.value,
            site: "all",
          })
        }
      >
        <option value="all">All Entities</option>

        {entities.map((e) => (
          <option key={e.id} value={e.id}>
            {e.name}
          </option>
        ))}
      </select>

      {/* SITE FILTER */}
      <select
        value={filters.site || "all"}
        onChange={(e) =>
          setFilters({ ...filters, site: e.target.value })
        }
      >
        <option value="all">All Sites</option>

        {filteredSites.map((site) => (
          <option key={site.site_id} value={site.site_id}>
            {site.name}
          </option>
        ))}
      </select>

      {/* PAYMENT FILTER */}
      <select
        value={filters.payment}
        onChange={(e) =>
          setFilters({ ...filters, payment: e.target.value })
        }
      >
        <option value="all">All Payments</option>
        <option value="card">Card</option>
        <option value="cash">Cash</option>
      </select>

    </div>
  );
}

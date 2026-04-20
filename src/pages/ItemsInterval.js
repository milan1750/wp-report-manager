import { useContext, useEffect, useState, useMemo } from "@wordpress/element";
import { FilterContext } from "../contexts";

/**
 * Utility helpers
 */
const toNumber = (v) => Number(v || 0);
const toFloat = (v) => Math.round((Number(v) || 0) * 100) / 100;

/**
 * Generic file download helper
 */
const downloadFile = (blob, filename) => {
  const url = window.URL.createObjectURL(blob);

  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();

  window.URL.revokeObjectURL(url);
};

export default function ItemsInterval() {
  const { filters, setFilters } = useContext(FilterContext);

  /** ========================
   * STATE
   * ====================== */
  const [data, setData] = useState({ slots: [], items: [] });
  const [loading, setLoading] = useState(true);

  const [exporting, setExporting] = useState({
    excel: false,
    pdf: false,
  });

  // Category handling (UI vs applied state separation)
  const [categories, setCategories] = useState([]);
  const [selectedCategories, setSelectedCategories] = useState([]);
  const [appliedCategories, setAppliedCategories] = useState([]);

  const [catOpen, setCatOpen] = useState(false);

  /** ========================
   * EFFECTS
   * ====================== */

  /**
   * Reset category selection when entity/site changes
   */
  useEffect(() => {
    setSelectedCategories([]);
    setAppliedCategories([]);
  }, [filters.entity, filters.site]);

  /**
   * Close dropdown when clicking outside
   */
  useEffect(() => {
    const handleClickOutside = (e) => {
      if (!e.target.closest(".category-dropdown")) {
        setCatOpen(false);
      }
    };

    document.addEventListener("click", handleClickOutside);
    return () => document.removeEventListener("click", handleClickOutside);
  }, []);

  /**
   * Initialize default filter state (only once or when invalid)
   */
  useEffect(() => {
    setFilters((prev) => {
      const today = new Date().toISOString().split("T")[0];

      if (prev.mode === "interval" && prev.interval_a && prev.interval_b) {
        return prev;
      }

      return {
        ...prev,
        mode: "interval",
        interval_a: today,
        interval_b: today,
        interval_a_preset: "same_day",
        interval_b_preset: "same_day",
        interval: 60,
      };
    });
  }, [setFilters]);

  /**
   * Build API query params
   * Only appliedCategories should trigger data fetch
   */
  const params = useMemo(() => {
    const p = new URLSearchParams({
      interval: filters.interval || 60,
      entity: filters.entity || "all",
      site: filters.site || "all",
      interval_a: filters.interval_a || "",
      interval_b: filters.interval_b || "",
      interval_a_preset: filters.interval_a_preset || "",
      interval_b_preset: filters.interval_b_preset || "",
    });

    if (appliedCategories.length) {
      p.append("categories", appliedCategories.join(","));
    }

    return p;
  }, [filters, appliedCategories]);

  /**
   * Fetch category list
   */
  useEffect(() => {
    const api = window.WRM_API;
    if (!api?.url) return;

    const query = new URLSearchParams({
      entity: filters.entity || "all",
      site: filters.site || "all",
    });

    fetch(`${api.url}reports/item-categories?${query}`, {
      headers: { "X-WP-Nonce": api.nonce },
    })
      .then((res) => res.json())
      .then((res) => setCategories(res?.data || []))
      .catch(() => setCategories([]));
  }, [filters.entity, filters.site]);

  /**
   * Fetch main report data
   */
  useEffect(() => {
    const api = window.WRM_API;
    if (!api?.url) return;

    setLoading(true);

    fetch(`${api.url}reports/items-interval?${params}`, {
      headers: { "X-WP-Nonce": api.nonce },
    })
      .then((res) => res.json())
      .then((res) => setData(res || { slots: [], items: [] }))
      .catch(() => setData({ slots: [], items: [] }))
      .finally(() => setLoading(false));
  }, [params]);

  /** ========================
   * DERIVED DATA
   * ====================== */

  const slots = data.slots || [];
  const itemsRaw = data.items || [];

  /**
   * Calculate totals per item
   */
  const itemsWithTotals = useMemo(() => {
    return itemsRaw.map((item) => {
      let thisTotal = 0;
      let lastTotal = 0;

      slots.forEach((slot) => {
        const s = item.slots?.[slot] || {};
        thisTotal += toNumber(s.this);
        lastTotal += toNumber(s.last);
      });

      return {
        ...item,
        thisTotal: toFloat(thisTotal),
        lastTotal: toFloat(lastTotal),
      };
    });
  }, [itemsRaw, slots]);

  /**
   * Sort items by current total descending
   */
  const sortedItems = useMemo(() => {
    return [...itemsWithTotals].sort((a, b) => b.thisTotal - a.thisTotal);
  }, [itemsWithTotals]);

  /**
   * Column totals
   */
  const columnTotals = useMemo(() => {
    const totals = {};

    slots.forEach((slot) => {
      let thisSum = 0;
      let lastSum = 0;

      itemsWithTotals.forEach((item) => {
        const s = item.slots?.[slot] || {};
        thisSum += toNumber(s.this);
        lastSum += toNumber(s.last);
      });

      totals[slot] = {
        this: toFloat(thisSum),
        last: toFloat(lastSum),
      };
    });

    return totals;
  }, [itemsWithTotals, slots]);

  const grandThis = useMemo(
    () =>
      toFloat(
        itemsWithTotals.reduce((sum, i) => sum + toNumber(i.thisTotal), 0),
      ),
    [itemsWithTotals],
  );

  const grandLast = useMemo(
    () =>
      toFloat(
        itemsWithTotals.reduce((sum, i) => sum + toNumber(i.lastTotal), 0),
      ),
    [itemsWithTotals],
  );

  const isSameDay = filters.interval_a === filters.interval_b;

  /** ========================
   * EXPORT HANDLERS
   * ====================== */

  const exportFile = async (type) => {
    const api = window.WRM_API;
    if (!api?.url) return;

    setExporting((prev) => ({ ...prev, [type]: true }));

    try {
      const query = new URLSearchParams({
        ...filters,
      });

      if (selectedCategories.length) {
        query.append("categories", selectedCategories.join(","));
      }

      const endpoint = type === "excel" ? "excel-download" : "pdf-download";

      const res = await fetch(
        `${api.url}reports/items-interval/${endpoint}?${query}`,
        {
          headers: { "X-WP-Nonce": api.nonce },
        },
      );

      if (!res.ok) throw new Error(`${type} export failed`);

      const blob = await res.blob();

      downloadFile(
        blob,
        `items-interval-report.${type === "excel" ? "xlsx" : "pdf"}`,
      );
    } finally {
      setExporting((prev) => ({ ...prev, [type]: false }));
    }
  };

  /** ========================
   * RENDER
   * ====================== */

  if (loading) {
    return <div className="sales">Loading...</div>;
  }

  return (
    <div className="item-interval">
      <div className="header-bar">
        <h1>Item Sales Report</h1>

        <div className="export-buttons">
          <div className="category-dropdown">
            <button
              type="button"
              className="btn btn-secondary"
              onClick={() => setCatOpen((v) => !v)}
            >
              {selectedCategories.length
                ? `${selectedCategories.length} Categories Selected`
                : "Select Categories"}
            </button>

            {catOpen && (
              <div className="dropdown-panel">
                <div className="dropdown-header">
                  <strong>Categories</strong>

                  <div>
                    <button
                      className="btn btn-primary"
                      onClick={() => {
                        setAppliedCategories(selectedCategories);
                        setCatOpen(false);
                      }}
                    >
                      Apply
                    </button>
                    &nbsp;
                    <button
                      type="button"
                      className="link-btn"
                      onClick={() => setSelectedCategories([])}
                    >
                      Clear
                    </button>
                  </div>
                </div>

                <div className="dropdown-list">
                  {categories.map((cat) => {
                    const checked = selectedCategories.includes(cat.name);

                    return (
                      <label key={cat.name} className="dropdown-item">
                        <input
                          type="checkbox"
                          checked={checked}
                          onChange={() => {
                            setSelectedCategories((prev) =>
                              checked
                                ? prev.filter((c) => c !== cat.name)
                                : [...prev, cat.name],
                            );
                          }}
                        />
                        <span>{cat.name}</span>
                        <small>({cat.count})</small>
                      </label>
                    );
                  })}
                </div>
              </div>
            )}
          </div>
          <div>
            <button
              className="btn btn-primary"
              onClick={() => exportFile("excel")}
              disabled={exporting.excel}
            >
              {exporting.excel ? "Exporting..." : "Export Excel"}
            </button>
            <button
              className="btn btn-secondary"
              onClick={() => exportFile("pdf")}
              disabled={exporting.pdf}
            >
              {exporting.pdf ? "Generating..." : "Export PDF"}
            </button>
          </div>
        </div>
      </div>

      <div className="table-card">
        <div className="table-scroll">
          <table className="table">
            <thead>
              <tr>
                <th>Item</th>

                {slots.map((slot) => (
                  <th key={slot}>
                    {slot}
                    <div className="muted">{isSameDay ? "Total" : "T / L"}</div>
                  </th>
                ))}

                <th>Total</th>
              </tr>
            </thead>

            <tbody>
              {sortedItems.map((item, i) => (
                <tr key={i}>
                  <td>{item.item_title}</td>

                  {slots.map((slot) => {
                    const s = item.slots?.[slot] || {};

                    return (
                      <td key={slot}>
                        {isSameDay ? (
                          <b>{toNumber(s.this)}</b>
                        ) : (
                          <>
                            <b>{toNumber(s.this)}</b>
                            <span className="muted"> / {toNumber(s.last)}</span>
                          </>
                        )}
                      </td>
                    );
                  })}

                  <td>
                    {isSameDay ? (
                      <b>{item.thisTotal}</b>
                    ) : (
                      <>
                        <b>{item.thisTotal}</b>
                        <span className="muted"> / {item.lastTotal}</span>
                      </>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>

            <tfoot>
              <tr>
                <th>Total</th>

                {slots.map((slot) => (
                  <th key={slot}>
                    {isSameDay
                      ? columnTotals[slot]?.this || 0
                      : `${columnTotals[slot]?.this || 0} / ${
                          columnTotals[slot]?.last || 0
                        }`}
                  </th>
                ))}

                <th>{isSameDay ? grandThis : `${grandThis} / ${grandLast}`}</th>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  );
}

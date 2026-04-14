import { useContext, useEffect, useMemo, useState } from "@wordpress/element";
import { FilterContext } from "../App";
import React from "react";

/* =========================
   HELPERS
========================= */

const money = (v) => {
  const n = Number(v || 0);
  return n.toLocaleString("en-GB", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

/* =========================
   COMPONENT
========================= */

export default function DailySalesSimple() {
  const { filters } = useContext(FilterContext);

  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);

  /* =========================
     FETCH
  ========================= */

  useEffect(() => {
    const api = window.WRM_API;
    if (!api?.url) return;

    setLoading(true);

    const params = new URLSearchParams({
      from: filters.from,
      to: filters.to,
    });

    if (filters.entity) params.append("entity", filters.entity);
    if (filters.site) params.append("site", filters.site);

    fetch(`${api.url}reports/daily-sales?${params.toString()}`, {
      headers: { "X-WP-Nonce": api.nonce },
    })
      .then((r) => r.json())
      .then((d) => setData(d?.data || null))
      .finally(() => setLoading(false));
  }, [filters]);

  /* =========================
     DERIVED
  ========================= */

  const sites = data?.sites || [];
  const days = data?.days || [];

  const siteMap = useMemo(() => {
    const m = {};
    sites.forEach((s) => (m[s.id] = s.name));
    return m;
  }, [sites]);

  const sortedSiteIds = useMemo(() => {
    return [...sites]
      .sort((a, b) => a.name.localeCompare(b.name))
      .map((s) => s.id);
  }, [sites]);

  /* =========================
     EXPORT
  ========================= */

  const exportExcel = async () => {
    const api = window.WRM_API;
    if (!api?.url || exporting) return;

    setExporting(true);

    try {
      const params = new URLSearchParams({
        from: filters.from,
        to: filters.to,
      });

      if (filters.entity) params.append("entity", filters.entity);
      if (filters.site) params.append("site", filters.site);

      const res = await fetch(
        `${api.url}reports/daily-sales/download?${params.toString()}`,
        {
          headers: { "X-WP-Nonce": api.nonce },
        },
      );

      const blob = await res.blob();
      const url = window.URL.createObjectURL(blob);

      const a = document.createElement("a");
      a.href = url;
      a.download = "daily-sales.xlsx";
      a.click();

      window.URL.revokeObjectURL(url);
    } finally {
      setExporting(false);
    }
  };

  if (loading) return <div>Loading...</div>;

  /* =========================
     RENDER
  ========================= */

  return (
    <div className="wrm-sales">
      {/* HEADER */}
      <div className="header-bar">
        <h1>Daily Sales</h1>

        <button
          className="wrm-btn wrm-btn-primary wrm-export-btn"
          onClick={exportExcel}
          disabled={exporting}
        >
          {exporting ? (
            <>
              <span className="wrm-spinner" />
              Exporting...
            </>
          ) : (
            "Export Excel"
          )}
        </button>
      </div>

      <div className="table-card">
        <table className="wrm-table">
          {/* =========================
              HEADER (EXCEL STYLE)
          ========================= */}
          <thead>
            <tr>
              <th rowSpan="2">Date</th>
              <th rowSpan="2">Day</th>
              <th rowSpan="2">WK</th>

              <th colSpan="4">Overall</th>

              {sortedSiteIds.map((id) => (
                <th key={id} colSpan="4">
                  {siteMap[id]}
                </th>
              ))}
            </tr>

            <tr>
              {/* Overall */}
              <th>Net</th>
              <th>VAT</th>
              <th>Gross</th>
              <th>Gratuity</th>

              {/* Sites */}
              {sortedSiteIds.map((id) => (
                <React.Fragment key={id}>
                  <th>Net</th>
                  <th>VAT</th>
                  <th>Gross</th>
                  <th>Gratuity</th>
                </React.Fragment>
              ))}
            </tr>
          </thead>

          {/* =========================
              BODY
          ========================= */}
          <tbody>
            {days.map((d, i) => {
              const overall = d.overall || {};

              return (
                <tr key={i}>
                  <td>{d.date}</td>
                  <td>{d.day}</td>
                  <td>{d.week || ""}</td>

                  {/* OVERALL */}
                  <td>{money(overall.net)}</td>
                  <td>{money(overall.vat)}</td>
                  <td>{money(overall.gross)}</td>
                  <td>{money(overall.gratuity)}</td>

                  {/* SITES */}
                  {sortedSiteIds.map((id) => {
                    const s = d.sites?.[id] || {};
                    return (
                      <React.Fragment key={id}>
                        <td>{money(s.net)}</td>
                        <td>{money(s.vat)}</td>
                        <td>{money(s.gross)}</td>
                        <td>{money(s.gratuity)}</td>
                      </React.Fragment>
                    );
                  })}
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}

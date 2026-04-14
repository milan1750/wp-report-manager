import { useContext, useEffect, useMemo, useState } from "@wordpress/element";
import { FilterContext } from "../App";
import React from "react";

/* =========================
   HELPERS
========================= */

const money = (v) => {
  const n = Math.round(Number(v || 0));
  return n.toLocaleString("en-GB"); // 👈 comma formatting
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
     FETCH DATA
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
     DERIVED DATA
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

  const totals = useMemo(() => {
    return days.reduce(
      (acc, d) => {
        Object.values(d.sites || {}).forEach((s) => {
          acc.net += Number(s.net || 0);
          acc.gross += Number(s.gross || 0);
        });
        return acc;
      },
      { net: 0, gross: 0 }
    );
  }, [days]);

  /* =========================
     EXPORT EXCEL
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
        method: "GET",
        headers: {
          "X-WP-Nonce": api.nonce,
        },
      }
    );

    if (!res.ok) throw new Error("Export failed");

    const blob = await res.blob();

    // =========================
    // GET FILENAME FROM HEADER
    // =========================
    const disposition = res.headers.get("Content-Disposition");
    let filename = "daily-sales.xlsx";

    if (disposition && disposition.includes("filename=")) {
      const match = disposition.match(/filename="?(.+?)"?$/);
      if (match?.[1]) {
        filename = match[1];
      }
    }

    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename; // 👈 backend filename used here
    document.body.appendChild(a);
    a.click();
    a.remove();

    window.URL.revokeObjectURL(url);
  } catch (e) {
    console.error(e);
  } finally {
    setExporting(false);
  }
};

  /* =========================
     LOADING
  ========================= */

  if (loading) {
    return (
      <div className="wrm-sales">
        <div className="header-bar">
          <div className="skeleton" style={{ width: 200, height: 20 }} />
        </div>
      </div>
    );
  }

  /* =========================
     RENDER
  ========================= */

  return (
    <div className="wrm-sales">

      {/* HEADER */}
      <div className="header-bar">
        <h1>Daily Sales</h1>

        <button
          className="wrm-btn wrm-btn-primary"
          onClick={exportExcel}
          disabled={exporting}
        >
          {exporting ? "Exporting..." : "Export Excel"}
        </button>
      </div>

      {/* TABLE */}
      <div className="table-card">
        <h2>Day Wise Sales (All Sites)</h2>

        <table className="wrm-table">

          <thead>
            <tr>
              <th rowSpan="2">Date</th>
              <th rowSpan="2">Day</th>

              {sortedSiteIds.map((id) => (
                <th key={id} colSpan="2">
                  {siteMap[id]}
                </th>
              ))}

              <th colSpan="2">TOTAL</th>
            </tr>

            <tr>
              {sortedSiteIds.map((id) => (
                <React.Fragment key={id}>
                  <th>Net</th>
                  <th>Gross</th>
                </React.Fragment>
              ))}

              <th>Net</th>
              <th>Gross</th>
            </tr>
          </thead>

          <tbody>
            {days.map((d, i) => {
              const rowTotals = Object.values(d.sites || {}).reduce(
                (acc, s) => {
                  acc.net += Number(s.net || 0);
                  acc.gross += Number(s.gross || 0);
                  return acc;
                },
                { net: 0, gross: 0 }
              );

              return (
                <tr key={i}>
                  <td>{d.date}</td>
                  <td>{d.day}</td>

                  {sortedSiteIds.map((id) => {
                    const s = d.sites?.[id] || {};
                    return (
                      <React.Fragment key={id}>
                        <td>{money(s.net)}</td>
                        <td>{money(s.gross)}</td>
                      </React.Fragment>
                    );
                  })}

                  <td>{money(rowTotals.net)}</td>
                  <td>{money(rowTotals.gross)}</td>
                </tr>
              );
            })}

            {/* TOTAL ROW */}
            <tr style={{ fontWeight: "bold", background: "#f3f4f6" }}>
              <td colSpan="2">TOTAL</td>

              {sortedSiteIds.map((id) => {
                let net = 0;
                let gross = 0;

                days.forEach((d) => {
                  net += Number(d.sites?.[id]?.net || 0);
                  gross += Number(d.sites?.[id]?.gross || 0);
                });

                return (
                  <React.Fragment key={id}>
                    <td>{money(net)}</td>
                    <td>{money(gross)}</td>
                  </React.Fragment>
                );
              })}

              <td>{money(totals.net)}</td>
              <td>{money(totals.gross)}</td>
            </tr>

          </tbody>
        </table>
      </div>
    </div>
  );
}

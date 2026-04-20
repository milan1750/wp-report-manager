import { useContext, useEffect, useMemo, useState } from "@wordpress/element";
import { FilterContext } from "../contexts";
import React from "react";

/* =========================
   HELPERS
========================= */

const num = (v) => Number(v || 0);

const money = (v) =>
  num(v).toLocaleString("en-GB", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

/* =========================
   COMPONENT
========================= */

export default function Items() {
  const { filters, setFilters } = useContext(FilterContext);

  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  /* =========================
     NORMALISE DATE (IMPORTANT FIX)
  ========================= */

  const from = filters.range?.from || filters.from || "";
  const to = filters.range?.to || filters.to || "";

  const entity = filters.entity;
  const site = filters.site;

  /* ================= INIT MODE FIX ================= */
  useEffect(() => {
    setFilters((prev) => {
      const today = new Date().toISOString().split("T")[0];

      // if already correct, do nothing
      if (prev.mode === "range" && prev.range?.from && prev.range?.to) {
        return prev;
      }

      return {
        ...prev,
        mode: "range",
        range: {
          from: prev.range?.from || today,
          to: prev.range?.to || today,
          preset: prev.range?.preset || "same_day",
        },
      };
    });
  }, [setFilters]);

  /* =========================
     FETCH
  ========================= */

  useEffect(() => {
    const api = window.WRM_API;
    if (!api?.url) return;

    setLoading(true);

    const params = new URLSearchParams({
      from,
      to,
    });

    if (entity && entity !== "all") params.append("entity", entity);
    if (site && site !== "all") params.append("site", site);

    fetch(`${api.url}reports/items?${params.toString()}`, {
      headers: { "X-WP-Nonce": api.nonce },
    })
      .then((res) => res.json())
      .then(setData)
      .finally(() => setLoading(false));
  }, [from, to, entity, site]);

  /* =========================
     SAFE DATA
  ========================= */

  const sites = data?.sites || [];
  const categories = data?.categories || [];
  const items = data?.items || [];
  const days = data?.days || [];

  /* =========================
     KPI (LIKE DASHBOARD)
  ========================= */

  const totalGross = useMemo(
    () => sites.reduce((a, b) => a + num(b?.gross), 0),
    [sites],
  );

  const totalNet = useMemo(
    () => sites.reduce((a, b) => a + num(b?.net), 0),
    [sites],
  );

  const totalQty = useMemo(
    () => sites.reduce((a, b) => a + num(b?.total_qty), 0),
    [sites],
  );

  const totalTax = useMemo(
    () => sites.reduce((a, b) => a + num(b?.tax), 0),
    [sites],
  );

  /* =========================
     LOADING
  ========================= */

  if (loading) {
    return (
      <div className="sales">
        <div className="header-bar">
          <div className="skeleton" style={{ width: 160, height: 20 }} />
          <div className="export-buttons">
            <div className="skeleton" style={{ width: 110, height: 32 }} />
            <div className="skeleton" style={{ width: 110, height: 32 }} />
          </div>
        </div>

        <div className="table-card">
          <div
            className="skeleton"
            style={{ width: 140, height: 18, marginBottom: 12 }}
          />

          <table className="table">
            <tbody>
              {Array.from({ length: 5 }).map((_, i) => (
                <tr key={i}>
                  {Array.from({ length: 6 }).map((_, j) => (
                    <td key={j}>
                      <div className="skeleton" style={{ height: 12 }} />
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    );
  }

  /* =========================
     EMPTY STATE
  ========================= */

  if (!sites.length && !items.length) {
    return (
      <div className="sales">
        <div
          className="table-card"
          style={{ textAlign: "center", padding: 30 }}
        >
          <h2>No Items Data</h2>
        </div>
      </div>
    );
  }

  /* =========================
     UI
  ========================= */

  return (
    <div className="sales">
      {/* HEADER */}
      <div className="header-bar">
        <h1>Items Report</h1>
      </div>

      {/* ================= KPI (FIXED LIKE DASHBOARD) ================= */}
      <div className="kpi">
        {[
          ["Sites", sites.length],
          ["Qty", num(totalQty).toFixed(2)],
          ["Gross", `£${money(totalGross)}`],
          ["Net", `£${money(totalNet)}`],
          ["Tax", `£${money(totalTax)}`],
        ].map(([label, value]) => (
          <div className="kpi__card" key={label}>
            <h4>{label}</h4>
            <p>{value}</p>
          </div>
        ))}
      </div>

      {/* ================= SITES TABLE ================= */}
      {sites.length > 0 && (
        <div className="table-card">
          <h2>Sites</h2>

          <table className="table">
            <thead>
              <tr>
                <th>Site</th>
                <th>Qty</th>
                <th>Gross</th>
                <th>Net</th>
                <th>Discount</th>
                <th>Tax</th>
              </tr>
            </thead>

            <tbody>
              {sites.map((s, i) => (
                <tr key={i}>
                  <td>{s.site_name}</td>
                  <td>{num(s.total_qty).toFixed(2)}</td>
                  <td>{money(s.gross)}</td>
                  <td>{money(s.net)}</td>
                  <td>{money(s.discount)}</td>
                  <td>{money(s.tax)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* ================= TOP ITEMS ================= */}
      {items.length > 0 && (
        <div className="table-card">
          <h2>Top Items</h2>

          <table className="table">
            <thead>
              <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Gross</th>
                <th>Tax</th>
              </tr>
            </thead>

            <tbody>
              {items.slice(0, 20).map((i, idx) => (
                <tr key={idx}>
                  <td>{i.item_title}</td>
                  <td>{num(i.total_qty).toFixed(2)}</td>
                  <td>{money(i.gross)}</td>
                  <td>{money(i.tax)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

import { useContext, useEffect, useState, useMemo } from "@wordpress/element";
import { FilterContext } from "../App";

import * as XLSX from "xlsx";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";

export default function Items() {
  const { filters } = useContext(FilterContext);

  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const api = window.WRM_API;
    if (!api?.url) return;

    setLoading(true);

    const params = new URLSearchParams({
      from: filters.from,
      to: filters.to,
      site: filters.site || "",
    });

    fetch(`${api.url}reports/items?${params.toString()}`, {
      headers: { "X-WP-Nonce": api.nonce },
    })
      .then((res) => res.json())
      .then(setData)
      .finally(() => setLoading(false));
  }, [filters]);

  /* =========================
     SAFE DATA
  ========================= */

  const sites = data?.sites || [];
  const categories = data?.categories || [];
  const items = data?.items || [];
  const days = data?.days || [];

  /* =========================
     HELPERS
  ========================= */

  const num = (v) => Number(v || 0);
  const money = (v) => `£${num(v).toFixed(2)}`;

  const totalGross = useMemo(
    () => sites.reduce((a, b) => a + num(b?.gross), 0),
    [sites],
  );

  const totalQty = useMemo(
    () => sites.reduce((a, b) => a + num(b?.total_qty), 0),
    [sites],
  );

  /* =========================
     EXPORT EXCEL
  ========================= */

  const exportExcel = () => {
    const wb = XLSX.utils.book_new();

    XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(sites), "Sites");

    XLSX.utils.book_append_sheet(
      wb,
      XLSX.utils.json_to_sheet(categories),
      "Categories",
    );

    XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(items), "Items");

    XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(days), "Days");

    XLSX.writeFile(wb, "items-report.xlsx");
  };

  /* =========================
     EXPORT PDF
  ========================= */

  const exportPDF = () => {
    const doc = new jsPDF();

    doc.text("Items Report", 14, 10);

    autoTable(doc, {
      startY: 20,
      head: [["Site", "Qty", "Gross", "Net", "Discount", "Tax"]],
      body: sites.map((s) => [
        s.site_name,
        num(s.total_qty).toFixed(2),
        money(s.gross),
        money(s.net),
        money(s.discount),
        money(s.tax),
      ]),
    });

    doc.addPage();
    doc.text("Top Items", 14, 10);

    autoTable(doc, {
      startY: 20,
      head: [["Item", "Qty", "Gross", "Tax"]],
      body: items.map((i) => [
        i.item_title,
        num(i.total_qty).toFixed(2),
        money(i.gross),
        money(i.tax),
      ]),
    });

    doc.save("items-report.pdf");
  };

  /* =========================
     LOADING
  ========================= */

  if (loading) {
    return (
      <div className="wrm-sales">
        {/* HEADER SKELETON */}
        <div className="header-bar">
          <div className="skeleton" style={{ width: 160, height: 20 }} />

          <div className="export-buttons">
            <div className="skeleton" style={{ width: 110, height: 32 }} />
            <div className="skeleton" style={{ width: 110, height: 32 }} />
          </div>
        </div>

        {/* SITE TABLE SKELETON */}
        <div className="table-card">
          <div
            className="skeleton"
            style={{ width: 140, height: 18, marginBottom: 12 }}
          />

          <table className="wrm-table">
            <thead>
              <tr>
                {Array.from({ length: 8 }).map((_, i) => (
                  <th key={i}>
                    <div
                      className="skeleton"
                      style={{ height: 12, width: "70%" }}
                    />
                  </th>
                ))}
              </tr>
            </thead>

            <tbody>
              {Array.from({ length: 5 }).map((_, i) => (
                <tr key={i}>
                  {Array.from({ length: 8 }).map((_, j) => (
                    <td key={j}>
                      <div
                        className="skeleton"
                        style={{ height: 12, width: "80%" }}
                      />
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* DAY TABLE SKELETON */}
        <div className="table-card">
          <div
            className="skeleton"
            style={{ width: 140, height: 18, marginBottom: 12 }}
          />

          <table className="wrm-table">
            <thead>
              <tr>
                {Array.from({ length: 7 }).map((_, i) => (
                  <th key={i}>
                    <div
                      className="skeleton"
                      style={{ height: 12, width: "70%" }}
                    />
                  </th>
                ))}
              </tr>
            </thead>

            <tbody>
              {Array.from({ length: 5 }).map((_, i) => (
                <tr key={i}>
                  {Array.from({ length: 7 }).map((_, j) => (
                    <td key={j}>
                      <div
                        className="skeleton"
                        style={{ height: 12, width: "75%" }}
                      />
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
      <div className="wrm-items">
        <div className="table-card">
          <h2>No Items Data</h2>
        </div>
      </div>
    );
  }

  /* =========================
     RENDER
  ========================= */

  return (
    <div className="wrm-items">
      {/* HEADER (FIXED - SAME AS SALES STYLE) */}
      <div className="header-bar">
        <h1 className="page-title">Items Report</h1>

        <div className="export-buttons">
          <button className="wrm-btn wrm-btn-primary" onClick={exportExcel}>
            Export Excel
          </button>

          <button className="wrm-btn wrm-btn-secondary" onClick={exportPDF}>
            Export PDF
          </button>
        </div>
      </div>

      {/* KPI (FIXED - NO WRAPPER BUGS) */}
      <div className="wrm-cards">
        <div className="card">
          <h4>Total Sites</h4>
          <p>{sites.length}</p>
        </div>

        <div className="card">
          <h4>Total Qty</h4>
          <p>{num(totalQty).toFixed(2)}</p>
        </div>

        <div className="card">
          <h4>Total Gross</h4>
          <p>{money(totalGross)}</p>
        </div>

        <div className="card">
          <h4>Total Items</h4>
          <p>{items.length}</p>
        </div>
      </div>

      {/* SITES TABLE */}
      {sites.length > 0 && (
        <div className="table-card">
          <h2>Sites</h2>

          <table className="wrm-table">
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

      {/* TOP ITEMS */}
      {items.length > 0 && (
        <div className="table-card">
          <h2>Top Items</h2>

          <table className="wrm-table">
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

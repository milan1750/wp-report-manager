import { useContext, useEffect, useState } from "@wordpress/element";
import { FilterContext } from "../App";

import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";

/* =========================
   HELPERS
========================= */

const money = (v) => Math.round(Number(v || 0));

const pct = (v) => Number(v || 0).toFixed(1);

const variancePct = (c = 0, p = 0) => {
  const cur = Number(c || 0);
  const prev = Number(p || 0);
  if (!prev) return 0;
  return ((cur - prev) / prev) * 100;
};

const varClass = (v) => {
  if (v < 0) return "var-red";
  if (v < 1) return "var-yellow";
  if (v < 5) return "var-light-green";
  return "var-green";
};

/* =========================
   GRATUITY SPLIT
========================= */

const splitGratuity = (g = 0) => {
  const total = Number(g || 0);
  return {
    gratuity9: total,
    eatIn35: total * (3.5 / 9),
  };
};

/* =========================
   VAT %
========================= */

const vatPct = (vat = 0, net = 0) => {
  if (!net) return 0;
  return (Number(vat) / Number(net)) * 100;
};

/* =========================
   COMPONENT
========================= */

export default function Sales() {
  const { filters } = useContext(FilterContext);

  const [sites, setSites] = useState([]);
  const [days, setDays] = useState([]);
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

    fetch(`${api.url}reports/sales?${params.toString()}`, {
      headers: { "X-WP-Nonce": api.nonce },
    })
      .then((r) => r.json())
      .then((d) => {
        setSites(d?.sites || []);
        setDays(d?.days || []);
      })
      .finally(() => setLoading(false));
  }, [filters]);

  /* =========================
     SUM HELPER
  ========================= */

  const sum = (arr, fn) =>
    arr.reduce((t, x) => t + Number(fn(x) || 0), 0);

  /* =========================
     TOTALS
  ========================= */

  const siteTotals = {
    netC: sum(sites, (x) => x.this?.net),
    netP: sum(sites, (x) => x.last?.net),
    grossC: sum(sites, (x) => x.this?.gross),
    grossP: sum(sites, (x) => x.last?.gross),
    vat: sum(sites, (x) => x.this?.vat),
    gratuity: sum(sites, (x) => x.this?.gratuity),
  };

  const siteGrat = splitGratuity(siteTotals.gratuity);

  const dayTotals = {
    netC: sum(days, (x) => x.this?.net),
    netP: sum(days, (x) => x.last?.net),
    grossC: sum(days, (x) => x.this?.gross),
    grossP: sum(days, (x) => x.last?.gross),
  };

  /* =========================
     EXCEL (API EXPORT)
  ========================= */

  const exportExcel = async () => {
    const api = window.WRM_API;
    if (!api?.url) return;

    setExporting(true);

    try {
      const params = new URLSearchParams({
        from: filters.from,
        to: filters.to,
      });

      if (filters.entity) params.append("entity", filters.entity);
      if (filters.site) params.append("site", filters.site);

      const res = await fetch(
        `${api.url}reports/sales/download?${params.toString()}`,
        {
          headers: { "X-WP-Nonce": api.nonce },
        }
      );

      if (!res.ok) throw new Error("Excel export failed");

      const blob = await res.blob();
      const url = window.URL.createObjectURL(blob);

      const a = document.createElement("a");
      a.href = url;
      a.download = "sales-report.xlsx";
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
     PDF EXPORT (FRONTEND)
  ========================= */

  const exportPDF = () => {
    const doc = new jsPDF("l", "mm", "a4");

    doc.text("Sales Report - Site Performance", 14, 10);

    autoTable(doc, {
      startY: 15,
      head: [[
        "Site",
        "Net C", "Net P", "Net Var%",
        "Gross C", "Gross P", "Gross Var%",
        "VAT", "VAT%",
        "Grat", "Eat"
      ]],
      body: sites.map((s) => {
        const g = splitGratuity(s.this?.gratuity);

        return [
          s.site,

          money(s.this?.net),
          money(s.last?.net),
          pct(variancePct(s.this?.net, s.last?.net)) + "%",

          money(s.this?.gross),
          money(s.last?.gross),
          pct(variancePct(s.this?.gross, s.last?.gross)) + "%",

          money(s.this?.vat),
          pct(vatPct(s.this?.vat, s.this?.net)) + "%",

          money(g.gratuity9),
          money(g.eatIn35),
        ];
      }),
      styles: { fontSize: 8 },
    });

    autoTable(doc, {
      startY: doc.lastAutoTable.finalY + 10,
      head: [["Day", "Net C", "Net P", "Net Var%", "Gross C", "Gross P", "Gross Var%"]],
      body: days.map((d) => [
        d.day,
        money(d.this?.net),
        money(d.last?.net),
        pct(variancePct(d.this?.net, d.last?.net)) + "%",

        money(d.this?.gross),
        money(d.last?.gross),
        pct(variancePct(d.this?.gross, d.last?.gross)) + "%",
      ]),
      styles: { fontSize: 8 },
    });

    doc.save("sales-report.pdf");
  };

  /* =========================
     LOADING
  ========================= */

  if (loading) {
    return <div className="wrm-sales">Loading...</div>;
  }

  /* =========================
     RENDER
  ========================= */

  return (
    <div className="wrm-sales">

      {/* HEADER */}
      <div className="header-bar">
        <h1>Sales Report</h1>

        <div className="export-buttons">
          <button
            className="wrm-btn wrm-btn-primary"
            onClick={exportExcel}
            disabled={exporting}
          >
            {exporting ? "Exporting..." : "Export Excel"}
          </button>

          <button className="wrm-btn wrm-btn-secondary" onClick={exportPDF}>
            Export PDF
          </button>
        </div>
      </div>

      {/* =========================
          SITE PERFORMANCE
      ========================= */}

      <div className="table-card">
        <h2>Site Performance</h2>

        <table className="wrm-table">
          <thead>
            <tr>
              <th rowSpan="2">Site</th>
              <th colSpan="3">Net Sales</th>
              <th colSpan="3">Gross Sales</th>
              <th colSpan="4">VAT / GRATUITY</th>
            </tr>

            <tr>
              <th>Current</th>
              <th>Previous</th>
              <th>Variance%</th>

              <th>Current</th>
              <th>Previous</th>
              <th>Variance%</th>

              <th>VAT</th>
              <th>VAT%</th>
              <th>Gratuity</th>
              <th>Eat in Charge</th>
            </tr>
          </thead>

          <tbody>
            {sites.map((s, i) => {
              const g = splitGratuity(s.this?.gratuity);

              return (
                <tr key={i}>
                  <td>{s.site}</td>

                  <td>{money(s.this?.net)}</td>
                  <td>{money(s.last?.net)}</td>
                  <td className={varClass(variancePct(s.this?.net, s.last?.net))}>
                    {pct(variancePct(s.this?.net, s.last?.net))}%
                  </td>

                  <td>{money(s.this?.gross)}</td>
                  <td>{money(s.last?.gross)}</td>
                  <td className={varClass(variancePct(s.this?.gross, s.last?.gross))}>
                    {pct(variancePct(s.this?.gross, s.last?.gross))}%
                  </td>

                  <td>{money(s.this?.vat)}</td>
                  <td>{pct(vatPct(s.this?.vat, s.this?.net))}%</td>

                  <td>{money(g.gratuity9)}</td>
                  <td>{money(g.eatIn35)}</td>
                </tr>
              );
            })}

            {/* TOTAL */}
            <tr style={{ fontWeight: "bold", background: "#f3f4f6" }}>
              <td>TOTAL</td>

              <td>{money(siteTotals.netC)}</td>
              <td>{money(siteTotals.netP)}</td>
              <td>{pct(variancePct(siteTotals.netC, siteTotals.netP))}%</td>

              <td>{money(siteTotals.grossC)}</td>
              <td>{money(siteTotals.grossP)}</td>
              <td>{pct(variancePct(siteTotals.grossC, siteTotals.grossP))}%</td>

              <td>{money(siteTotals.vat)}</td>
              <td>{pct(vatPct(siteTotals.vat, siteTotals.netC))}%</td>

              <td>{money(siteGrat.gratuity9)}</td>
              <td>{money(siteGrat.eatIn35)}</td>
            </tr>
          </tbody>
        </table>
      </div>

      {/* =========================
          DAY PERFORMANCE
      ========================= */}

      <div className="table-card">
        <h2>Day Performance</h2>

        <table className="wrm-table">
          <thead>
            <tr>
              <th>Day</th>
              <th>Net C</th>
              <th>Net P</th>
              <th>Net Var%</th>
              <th>Gross C</th>
              <th>Gross P</th>
              <th>Gross Var%</th>
            </tr>
          </thead>

          <tbody>
            {days.map((d, i) => (
              <tr key={i}>
                <td>{d.day}</td>

                <td>{money(d.this?.net)}</td>
                <td>{money(d.last?.net)}</td>
                <td className={varClass(variancePct(d.this?.net, d.last?.net))}>
                  {pct(variancePct(d.this?.net, d.last?.net))}%
                </td>

                <td>{money(d.this?.gross)}</td>
                <td>{money(d.last?.gross)}</td>
                <td className={varClass(variancePct(d.this?.gross, d.last?.gross))}>
                  {pct(variancePct(d.this?.gross, d.last?.gross))}%
                </td>
              </tr>
            ))}

            <tr style={{ fontWeight: "bold", background: "#f3f4f6" }}>
              <td>TOTAL</td>

              <td>{money(dayTotals.netC)}</td>
              <td>{money(dayTotals.netP)}</td>
              <td>{pct(variancePct(dayTotals.netC, dayTotals.netP))}%</td>

              <td>{money(dayTotals.grossC)}</td>
              <td>{money(dayTotals.grossP)}</td>
              <td>{pct(variancePct(dayTotals.grossC, dayTotals.grossP))}%</td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  );
}

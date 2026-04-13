import { useContext, useEffect, useState } from "@wordpress/element";
import { FilterContext } from "../App";

import * as XLSX from "xlsx";
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
     PIVOT TOTALS (SITE)
  ========================= */

  const sum = (arr, path) =>
    arr.reduce((t, x) => t + Number(path(x) || 0), 0);

  const siteTotals = {
    netC: sum(sites, (x) => x.this?.net),
    netP: sum(sites, (x) => x.last?.net),

    grossC: sum(sites, (x) => x.this?.gross),
    grossP: sum(sites, (x) => x.last?.gross),

    vat: sum(sites, (x) => x.this?.vat),
    gratuity: sum(sites, (x) => x.this?.gratuity),
  };

  const siteGrat = splitGratuity(siteTotals.gratuity);

  /* =========================
     PIVOT TOTALS (DAY)
  ========================= */

  const dayTotals = {
    netC: sum(days, (x) => x.this?.net),
    netP: sum(days, (x) => x.last?.net),

    grossC: sum(days, (x) => x.this?.gross),
    grossP: sum(days, (x) => x.last?.gross),
  };

  /* =========================
     EXPORT EXCEL
  ========================= */

  const exportExcel = () => {
    const siteSheet = sites.map((s) => {
      const g = splitGratuity(s.this?.gratuity);

      return {
        Site: s.site,

        Net_C: money(s.this?.net),
        Net_P: money(s.last?.net),
        Net_Var: pct(variancePct(s.this?.net, s.last?.net)) + "%",

        Gross_C: money(s.this?.gross),
        Gross_P: money(s.last?.gross),
        Gross_Var: pct(variancePct(s.this?.gross, s.last?.gross)) + "%",

        VAT: money(s.this?.vat),
        VAT_Pct: pct(vatPct(s.this?.vat, s.this?.net)) + "%",

        Gratuity: money(g.gratuity9),
        EatIn: money(g.eatIn35),
      };
    });

    const daySheet = days.map((d) => ({
      Day: d.day,
      Net_C: money(d.this?.net),
      Net_P: money(d.last?.net),
      Net_Var: pct(variancePct(d.this?.net, d.last?.net)) + "%",
      Gross_C: money(d.this?.gross),
      Gross_P: money(d.last?.gross),
      Gross_Var: pct(variancePct(d.this?.gross, d.last?.gross)) + "%",
    }));

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(siteSheet), "Sites");
    XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(daySheet), "Days");
    XLSX.writeFile(wb, "sales-report.xlsx");
  };

  /* =========================
     EXPORT PDF
  ========================= */

  const exportPDF = () => {
    const doc = new jsPDF();

    doc.text("Sales Report", 14, 10);

    autoTable(doc, {
      startY: 15,
      head: [[
        "Site",
        "Net C", "Net P", "Net Var",
        "Gross C", "Gross P", "Gross Var",
        "VAT", "VAT%", "Grat", "Eat"
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
    });

    doc.save("sales-report.pdf");
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
     RENDER
  ========================= */

  return (
    <div className="wrm-sales">

      {/* HEADER */}
      <div className="header-bar">
        <h1>Sales Report</h1>

       <div className="export-buttons">
          <button className="wrm-btn wrm-btn-primary" onClick={exportExcel}>
            Export Excel
          </button>

          <button className="wrm-btn wrm-btn-secondary" onClick={exportPDF}>
            Export PDF
          </button>
        </div>
      </div>

      {/* =========================
          SITE TABLE (PIVOT GROUPED)
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

            {/* =========================
                PIVOT TOTAL ROW (FULL)
            ========================= */}

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
          DAY TABLE (PIVOT)
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

            {/* DAY TOTAL */}
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

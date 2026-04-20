import { useContext, useEffect, useState } from "@wordpress/element";
import { FilterContext } from "../contexts";

import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";

/* ================= FORMAT HELPERS ================= */

const money = (v) =>
  Number(v || 0).toLocaleString("en-GB", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

const pct = (v) =>
  Number(v || 0).toLocaleString("en-GB", {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  });

const variancePct = (c = 0, p = 0) => {
  const cur = Number(c || 0);
  const prev = Number(p || 0);
  if (!prev) return 0;
  return ((cur - prev) / prev) * 100;
};

const varClass = (v) => {
  const n = Number(v || 0);
  if (n <= -10) return "var-worst";
  if (n <= -3) return "var-bad";
  if (n < 0) return "var-poor";
  if (n < 3) return "var-neutral";
  if (n < 8) return "var-good";
  return "var-best";
};

const splitGratuity = (g = 0) => {
  const total = Number(g || 0);
  return {
    gratuity9: total,
    eatIn35: total * (3.5 / 9),
  };
};

const vatPct = (vat = 0, net = 0) => {
  if (!net) return 0;
  return (Number(vat) / Number(net)) * 100;
};

/* ================= COMPONENT ================= */

export default function Sales() {
  const { filters, setFilters } = useContext(FilterContext);
  const [sites, setSites] = useState([]);
  const [days, setDays] = useState([]);
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);

  /* ================= FIX: SAFE MODE + FILTER SYNC ================= */

  const isRangeMode = filters.mode === "range";

  const from = isRangeMode ? filters.range?.from || "" : "";
  const to = isRangeMode ? filters.range?.to || "" : "";

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
  /* ================= FETCH ================= */

  useEffect(() => {
    const api = window.WRM_API;
    if (!api?.url) return;

    setLoading(true);

    const params = new URLSearchParams({
      from,
      to,
    });

    if (filters.entity && filters.entity !== "all") {
      params.append("entity", filters.entity);
    }

    if (filters.site && filters.site !== "all") {
      params.append("site", filters.site);
    }

    fetch(`${api.url}reports/sales?${params.toString()}`, {
      headers: { "X-WP-Nonce": api.nonce },
    })
      .then((r) => r.json())
      .then((d) => {
        setSites(d?.sites || []);
        setDays(d?.days || []);
      })
      .finally(() => setLoading(false));
  }, [
    filters.range?.from,
    filters.range?.to,
    filters.entity,
    filters.site,
    filters.mode,
  ]);

  /* ================= TOTALS ================= */

  const sum = (arr, fn) => arr.reduce((t, x) => t + Number(fn(x) || 0), 0);

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

  /* ================= EXPORT ================= */

  const exportExcel = async () => {
    const api = window.WRM_API;
    if (!api?.url) return;

    setExporting(true);

    try {
      const params = new URLSearchParams({ from, to });

      if (filters.entity) params.append("entity", filters.entity);
      if (filters.site) params.append("site", filters.site);

      const res = await fetch(
        `${api.url}reports/sales/download?${params.toString()}`,
        {
          headers: { "X-WP-Nonce": api.nonce },
        },
      );

      const blob = await res.blob();
      const url = window.URL.createObjectURL(blob);

      const a = document.createElement("a");
      a.href = url;
      a.download = "sales-report.xlsx";
      a.click();

      window.URL.revokeObjectURL(url);
    } finally {
      setExporting(false);
    }
  };

  const exportPDF = () => {
    const doc = new jsPDF("l", "mm", "a4");

    doc.text("Sales Report", 14, 10);

    autoTable(doc, {
      startY: 15,
      head: [["Site", "Net", "Gross"]],
      body: sites.map((s) => [
        s.site,
        money(s.this?.net),
        money(s.this?.gross),
      ]),
    });

    doc.save("sales-report.pdf");
  };

  /* ================= UI ================= */

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

  return (
    <div className="sales">
      {/* HEADER */}
      <div className="header-bar">
        <h1>Sales Report</h1>

        <div className="export-buttons">
          <button
            className="btn btn-primary"
            onClick={exportExcel}
            disabled={exporting}
          >
            {exporting ? "Exporting..." : "Export Excel"}
          </button>

          <button className="btn btn-secondary" onClick={exportPDF}>
            Export PDF
          </button>
        </div>
      </div>

      {/* SITE TABLE */}
      <div className="table-card">
        <h2>Site Performance</h2>
				<div className=" table-scroll">
        <table className="table">
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
              <th>Grat</th>
              <th>Eat</th>
            </tr>
          </thead>

          <tbody>
            {sites.map((s, i) => {
              const g = splitGratuity(s.this?.gratuity);

              const netV = variancePct(s.this?.net, s.last?.net);
              const grossV = variancePct(s.this?.gross, s.last?.gross);
              const vatV = vatPct(s.this?.vat, s.this?.net);

              return (
                <tr key={i}>
                  <td>{s.site}</td>

                  <td>{money(s.this?.net)}</td>
                  <td>{money(s.last?.net)}</td>
                  <td>
                    <span className={varClass(netV)}>{pct(netV)}%</span>
                  </td>

                  <td>{money(s.this?.gross)}</td>
                  <td>{money(s.last?.gross)}</td>
                  <td>
                    <span className={varClass(grossV)}>{pct(grossV)}%</span>
                  </td>

                  <td>{money(s.this?.vat)}</td>
                  <td>
                    <span className={varClass(vatV)}>{pct(vatV)}%</span>
                  </td>

                  <td>{money(g.gratuity9)}</td>
                  <td>{money(g.eatIn35)}</td>
                </tr>
              );
            })}

            <tr className="row-total">
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
      </div>

      {/* DAY TABLE */}
      <div className="table-card">
        <h2>Day Performance</h2>
					<div className=" table-scroll">
        <table className="table table-scroll">
          <thead>
            <tr>
              <th rowSpan="2">Day</th>
              <th colSpan="3">Net Sales</th>
              <th colSpan="3">Gross Sales</th>
            </tr>
            <tr>
              <th>Current</th>
              <th>Previous</th>
              <th>Variance%</th>

              <th>Current</th>
              <th>Previous</th>
              <th>Variance%</th>
            </tr>
          </thead>

          <tbody>
            {days.map((d, i) => {
              const netV = variancePct(d.this?.net, d.last?.net);
              const grossV = variancePct(d.this?.gross, d.last?.gross);

              return (
                <tr key={i}>
                  <td>{d.day}</td>

                  <td>{money(d.this?.net)}</td>
                  <td>{money(d.last?.net)}</td>
                  <td>
                    <span className={varClass(netV)}>{pct(netV)}%</span>
                  </td>

                  <td>{money(d.this?.gross)}</td>
                  <td>{money(d.last?.gross)}</td>
                  <td>
                    <span className={varClass(grossV)}>{pct(grossV)}%</span>
                  </td>
                </tr>
              );
            })}

            <tr className="row-total">
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
    </div>
  );
}

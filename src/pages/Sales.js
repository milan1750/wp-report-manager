import { useContext, useEffect, useState } from "@wordpress/element";
import { FilterContext } from "../App";

import * as XLSX from "xlsx";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";

export default function Sales() {
  const { filters } = useContext(FilterContext);

  const [sites, setSites] = useState([]);
  const [days, setDays] = useState([]);
  const [loading, setLoading] = useState(true);

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
      .then((res) => res.json())
      .then((data) => {
        setSites(data?.sites || []);
        setDays(data?.days || []);
      })
      .finally(() => setLoading(false));

  }, [filters.from, filters.to, filters.entity, filters.site]);

  // =========================
  // HELPERS
  // =========================
  const money = (v) => Number(v || 0).toFixed(2);

  const variancePct = (c = 0, p = 0) =>
    p ? ((c - p) / p) * 100 : 0;

  const vatPct = (vat = 0, gross = 0) =>
    gross ? (vat / gross) * 100 : 0;

  const getVarClass = (v) => {
    if (v < 0) return "var-red";
    if (v < 1) return "var-yellow";
    if (v < 5) return "var-light-green";
    return "var-green";
  };

  // =========================
  // EXPORT EXCEL
  // =========================
  const exportExcel = () => {

    const siteSheet = sites.map((s) => ({
      Site: s.site,
      Net_Current: s.this.net,
      Net_Previous: s.last.net,
      Net_Var: variancePct(s.this.net, s.last.net).toFixed(2) + "%",
      Gross_Current: s.this.gross,
      Gross_Previous: s.last.gross,
      Gross_Var: variancePct(s.this.gross, s.last.gross).toFixed(2) + "%",
      VAT: s.this.vat,
      VAT_Percent: vatPct(s.this.vat, s.this.gross).toFixed(2) + "%",
    }));

    const daySheet = days.map((d) => ({
      Day: d.day,
      Net_Current: d.this.net,
      Net_Previous: d.last.net,
      Net_Var: variancePct(d.this.net, d.last.net).toFixed(2) + "%",
      Gross_Current: d.this.gross,
      Gross_Previous: d.last.gross,
      Gross_Var: variancePct(d.this.gross, d.last.gross).toFixed(2) + "%",
    }));

    const wb = XLSX.utils.book_new();

    XLSX.utils.book_append_sheet(
      wb,
      XLSX.utils.json_to_sheet(siteSheet),
      "Sites"
    );

    XLSX.utils.book_append_sheet(
      wb,
      XLSX.utils.json_to_sheet(daySheet),
      "Days"
    );

    XLSX.writeFile(wb, "sales-report.xlsx");
  };

  // =========================
  // EXPORT PDF
  // =========================
  const exportPDF = () => {

    const doc = new jsPDF();

    doc.text("Sales Report - Sites", 14, 10);

    autoTable(doc, {
      startY: 15,
      head: [
        [
          "Site",
          "Net C",
          "Net P",
          "Net Var%",
          "Gross C",
          "Gross P",
          "Gross Var%",
          "VAT",
          "VAT%",
        ],
      ],
      body: sites.map((s) => [
        s.site,
        money(s.this.net),
        money(s.last.net),
        variancePct(s.this.net, s.last.net).toFixed(2) + "%",
        money(s.this.gross),
        money(s.last.gross),
        variancePct(s.this.gross, s.last.gross).toFixed(2) + "%",
        money(s.this.vat),
        vatPct(s.this.vat, s.this.gross).toFixed(2) + "%",
      ]),
    });

    doc.addPage();

    doc.text("Sales Report - Days", 14, 10);

    autoTable(doc, {
      startY: 15,
      head: [["Day", "Net C", "Net P", "Net Var%", "Gross C", "Gross P", "Gross Var%"]],
      body: days.map((d) => [
        d.day,
        money(d.this.net),
        money(d.last.net),
        variancePct(d.this.net, d.last.net).toFixed(2) + "%",
        money(d.this.gross),
        money(d.last.gross),
        variancePct(d.this.gross, d.last.gross).toFixed(2) + "%",
      ]),
    });

    doc.save("sales-report.pdf");
  };

  // =========================
  // STATES
  // =========================

  if (loading) {
    return <div className="loading">Loading sales report...</div>;
  }

  if (!sites.length && !days.length) {
    return (
      <div className="empty-state">
        No sales data available for the selected period.
      </div>
    );
  }

  // =========================
  // UI
  // =========================

  return (
    <div className="sales-page">

      <div className="header-bar">
        <h1 className="page-title">Sales Report</h1>

        <div className="export-buttons">
          <button onClick={exportExcel} disabled={!sites.length}>
            Export Excel
          </button>

          <button onClick={exportPDF} disabled={!sites.length}>
            Export PDF
          </button>
        </div>
      </div>

      {/* SITE TABLE */}
      <div className="table-card">

        <h2>Site Performance</h2>

        <table className="wrm-table">

          <thead>
            <tr>
              <th>Site</th>
              <th>Net C</th>
              <th>Net P</th>
              <th>Net Var%</th>
              <th>Gross C</th>
              <th>Gross P</th>
              <th>Gross Var%</th>
              <th>VAT</th>
              <th>VAT%</th>
            </tr>
          </thead>

          <tbody>
            {sites.map((s, i) => {

              const netVar = variancePct(s.this.net, s.last.net);
              const grossVar = variancePct(s.this.gross, s.last.gross);
              const vatP = vatPct(s.this.vat, s.this.gross);

              return (
                <tr key={i}>
                  <td>{s.site}</td>

                  <td>{money(s.this.net)}</td>
                  <td>{money(s.last.net)}</td>
                  <td className={getVarClass(netVar)}>
                    {netVar.toFixed(1)}%
                  </td>

                  <td>{money(s.this.gross)}</td>
                  <td>{money(s.last.gross)}</td>
                  <td className={getVarClass(grossVar)}>
                    {grossVar.toFixed(1)}%
                  </td>

                  <td>{money(s.this.vat)}</td>
                  <td>{vatP.toFixed(2)}%</td>
                </tr>
              );
            })}
          </tbody>

        </table>
      </div>

      {/* DAY TABLE */}
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
            {days.map((d, i) => {

              const netVar = variancePct(d.this.net, d.last.net);
              const grossVar = variancePct(d.this.gross, d.last.gross);

              return (
                <tr key={i}>
                  <td>{d.day}</td>

                  <td>{money(d.this.net)}</td>
                  <td>{money(d.last.net)}</td>
                  <td className={getVarClass(netVar)}>
                    {netVar.toFixed(1)}%
                  </td>

                  <td>{money(d.this.gross)}</td>
                  <td>{money(d.last.gross)}</td>
                  <td className={getVarClass(grossVar)}>
                    {grossVar.toFixed(1)}%
                  </td>
                </tr>
              );
            })}
          </tbody>

        </table>
      </div>

    </div>
  );
}

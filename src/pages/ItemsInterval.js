import { useContext, useEffect, useState, useMemo } from "@wordpress/element";
import { FilterContext } from "../contexts";
import * as XLSX from "xlsx";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";

const num = (v) => Number(v || 0);

export default function ItemsInterval() {
  const { filters, setFilters } = useContext(FilterContext);

  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [exportingExcel, setExportingExcel] = useState(false);
  const [exportingPDF, setExportingPDF] = useState(false);
  /* INIT */
  useEffect(() => {
    setFilters((prev) => {
      const today = new Date().toISOString().split("T")[0];

      const alreadyCorrect =
        prev.mode === "interval" && prev.interval_a && prev.interval_b;

      if (alreadyCorrect) return prev;

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

  /* PARAMS */
  const params = useMemo(() => {
    return new URLSearchParams({
      interval: filters.interval || 60,
      entity: filters.entity || "all",
      site: filters.site || "all",
      interval_a: filters.interval_a || "",
      interval_b: filters.interval_b || "",
      interval_a_preset: filters.interval_a_preset || "",
      interval_b_preset: filters.interval_b_preset || "",
    });
  }, [filters]);

  /* FETCH */
  useEffect(() => {
    const api = window.WRM_API;
    if (!api?.url) return;

    setLoading(true);

    fetch(`${api.url}reports/items-interval?${params.toString()}`, {
      headers: { "X-WP-Nonce": api.nonce },
    })
      .then((res) => res.json())
      .then((d) => setData(d || { slots: [], items: [] }))
      .finally(() => setLoading(false));
  }, [params]);

  /* SAFE DATA */
  const slots = data?.slots || [];
  const itemsRaw = data?.items || [];

  /* TOTALS PER ITEM */
  const itemsWithTotals = useMemo(() => {
    return itemsRaw.map((item) => {
      let thisTotal = 0;
      let lastTotal = 0;

      slots.forEach((slot) => {
        const s = item.slots?.[slot] || { this: 0, last: 0 };

        thisTotal = Math.round((thisTotal + num(s.this)) * 1000) / 1000;
        lastTotal = Math.round((lastTotal + num(s.last)) * 1000) / 1000;
      });

      return { ...item, thisTotal, lastTotal };
    });
  }, [itemsRaw, slots]);

  const sortedItems = useMemo(() => {
    return [...itemsWithTotals].sort((a, b) => b.thisTotal - a.thisTotal);
  }, [itemsWithTotals]);

  /* COLUMN TOTALS */
  const columnTotals = useMemo(() => {
    const totals = {};

    slots.forEach((slot) => {
      totals[slot] = { this: 0, last: 0 };

      itemsWithTotals.forEach((item) => {
        const s = item.slots?.[slot] || { this: 0, last: 0 };

        totals[slot].this =
          Math.round((totals[slot].this + num(s.this)) * 100) / 100;

        totals[slot].last =
          Math.round((totals[slot].last + num(s.last)) * 100) / 100;
      });
    });

    return totals;
  }, [itemsWithTotals, slots]);

  const round2 = (v) => Math.round((v || 0) * 100) / 100;

  const grandThis = round2(
    itemsWithTotals.reduce((a, i) => a + i.thisTotal, 0),
  );

  const grandLast = round2(
    itemsWithTotals.reduce((a, i) => a + i.lastTotal, 0),
  );

  const isSameDay =
    filters.interval_a &&
    filters.interval_b &&
    filters.interval_a === filters.interval_b;

  /* EXPORT EXCEL */
  const exportExcel = async () => {
    const api = window.WRM_API;
    if (!api?.url) return;

    setExportingExcel(true);

    try {
      const params = new URLSearchParams({
        interval: filters.interval || 60,
        entity: filters.entity || "all",
        site: filters.site || "all",
        interval_a: filters.interval_a || "",
        interval_b: filters.interval_b || "",
      });

      const res = await fetch(
        `${api.url}reports/items-interval/excel-download?${params.toString()}`,
        {
          headers: { "X-WP-Nonce": api.nonce },
        },
      );

      const blob = await res.blob();
      const url = window.URL.createObjectURL(blob);

      const a = document.createElement("a");
      a.href = url;
      a.download = "items-interval-report.xlsx";
      document.body.appendChild(a);
      a.click();
      a.remove();

      window.URL.revokeObjectURL(url);
    } finally {
      setExportingExcel(false);
    }
  };

  /* EXPORT PDF */
  const exportPDF = async () => {
    const api = window.WRM_API;
    if (!api?.url) return;

    setExportingPDF(true);

    try {
      const params = new URLSearchParams({
        interval: filters.interval || 60,
        entity: filters.entity || "all",
        site: filters.site || "all",
        interval_a: filters.interval_a || "",
        interval_b: filters.interval_b || "",
        interval_a_preset: filters.interval_a_preset || "",
        interval_b_preset: filters.interval_b_preset || "",
      });

      const res = await fetch(
        `${api.url}reports/items-interval/pdf-download?${params.toString()}`,
        {
          headers: { "X-WP-Nonce": api.nonce },
        },
      );

      if (!res.ok) throw new Error("PDF export failed");

      const blob = await res.blob();
      const url = window.URL.createObjectURL(blob);

      const a = document.createElement("a");
      a.href = url;
      a.download = "items-interval-report.pdf";
      document.body.appendChild(a);
      a.click();
      a.remove();

      window.URL.revokeObjectURL(url);
    } finally {
      setExportingPDF(false);
    }
  };

  /* LOADING */
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
  if (!sortedItems.length) {
    return (
      <div className="sales">
        <div className="table-card empty">
          <h2>No Interval Data</h2>
          <p>Please select the correct date.</p>
        </div>
      </div>
    );
  }

  /* UI */
  return (
    <div className="item-interval">
      <div className="header-bar">
        <h1>Item Sales Report</h1>

        <div className="export-buttons">
          <button
            className="btn btn-primary"
            onClick={exportExcel}
            disabled={exportingExcel}
          >
            {exportingExcel ? "Exporting Excel..." : "Export Excel"}
          </button>

          <button
            className="btn btn-secondary"
            onClick={exportPDF}
            disabled={exportingPDF}
          >
            {exportingPDF ? "Generating PDF..." : "Export PDF"}
          </button>
        </div>
      </div>

      <div className="table-card">
        <div className="table-scroll">
          <table className="table ">
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
                    const s = item.slots?.[slot] || { this: 0, last: 0 };

                    return (
                      <td key={slot}>
                        {isSameDay ? (
                          <b>{num(s.this)}</b>
                        ) : (
                          <>
                            <b>{num(s.this)}</b>
                            <span className="muted"> / {num(s.last)}</span>
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

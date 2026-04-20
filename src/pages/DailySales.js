import { useContext, useEffect, useMemo, useState } from "@wordpress/element";
import { FilterContext } from "../contexts";
import React from "react";

/* ================= HELPERS ================= */

const money = (v) => {
  const n = Number(v || 0);
  return n.toLocaleString("en-GB", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

/* ================= COMPONENT ================= */

export default function DailySalesSimple() {
  const { filters } = useContext(FilterContext);

  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);
  const [flatExporting, setFlatExporting] = useState(false);

  /* ================= FIX: correct filter mapping ================= */

  const from = filters.range?.from || "";
  const to = filters.range?.to || "";

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

    fetch(`${api.url}reports/daily-sales?${params.toString()}`, {
      headers: { "X-WP-Nonce": api.nonce },
    })
      .then((r) => r.json())
      .then((d) => setData(d?.data || null))
      .finally(() => setLoading(false));
  }, [from, to, filters.entity, filters.site]);

  /* ================= DERIVED ================= */

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

  /* ================= EXPORT (UNCHANGED LOGIC) ================= */

  const exportExcel = async () => {
    const api = window.WRM_API;
    if (!api?.url || exporting) return;

    setExporting(true);

    try {
      const params = new URLSearchParams({
        from,
        to,
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

      const disposition = res.headers.get("Content-Disposition");
      let filename = "daily-sales.xlsx";

      if (disposition) {
        const match = disposition.match(/filename="?([^"]+)"?/);
        if (match?.[1]) filename = match[1];
      }

      const url = window.URL.createObjectURL(blob);

      const a = document.createElement("a");
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      a.remove();

      window.URL.revokeObjectURL(url);
    } finally {
      setExporting(false);
    }
  };

  const exportFlatExcel = async () => {
    const api = window.WRM_API;
    if (!api?.url || exporting) return;

    setFlatExporting(true);

    try {
      const params = new URLSearchParams({
        from,
        to,
      });

      if (filters.entity) params.append("entity", filters.entity);
      if (filters.site) params.append("site", filters.site);

      const res = await fetch(
        `${api.url}reports/daily-sales/download-flat?${params.toString()}`,
        {
          headers: { "X-WP-Nonce": api.nonce },
        },
      );

      const blob = await res.blob();

      const disposition = res.headers.get("Content-Disposition");
      let filename = "daily-sales.xlsx";

      if (disposition) {
        const match = disposition.match(/filename="?([^"]+)"?/);
        if (match?.[1]) filename = match[1];
      }

      const url = window.URL.createObjectURL(blob);

      const a = document.createElement("a");
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      a.remove();

      window.URL.revokeObjectURL(url);
    } finally {
      setFlatExporting(false);
    }
  };

  /* ================= LOADING UI (UNCHANGED) ================= */

  if (loading) {
    return (
      <div className="page">
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
      </div>
    );
  }

  /* ================= RENDER (UNCHANGED UI) ================= */

  return (
    <div className="page">
      <div className="header-bar">
        <h1>Daily Sales</h1>

        <div className="export-buttons">
          <button
            className="btn btn-primary"
            onClick={exportExcel}
            disabled={exporting}
          >
            {exporting ? "Exporting..." : "Export Excel"}
          </button>

          <button
            className="btn btn-primary"
            onClick={exportFlatExcel}
            disabled={flatExporting}
          >
            {flatExporting ? "Exporting..." : "Export Flat Excel"}
          </button>
        </div>
      </div>

      <div className="section">
        <div className="table-card">
					<div className="table-scroll">
						<table className="table">
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
									<th>Net</th>
									<th>VAT</th>
									<th>Gross</th>
									<th>Gratuity</th>

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

							<tbody>
								{days.map((d, i) => {
									const overall = d.overall || {};

									return (
										<tr key={i}>
											<td>{d.date}</td>
											<td>{d.day}</td>
											<td>{d.week || ""}</td>

											<td>{money(overall.net)}</td>
											<td>{money(overall.vat)}</td>
											<td>{money(overall.gross)}</td>
											<td>{money(overall.gratuity)}</td>

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
      </div>
    </div>
  );
}

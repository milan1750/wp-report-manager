import { useContext, useEffect, useRef, useState } from "@wordpress/element";
import { FilterContext } from "../contexts";

export default function FilterBar() {
  const { filters, setFilters, weeksData } = useContext(FilterContext);

  const mode = filters.mode || "range";

  const [openRange, setOpenRange] = useState(false);
  const [openA, setOpenA] = useState(false);
  const [openB, setOpenB] = useState(false);
  const [openInterval, setOpenInterval] = useState(false);

  const rangeRef = useRef(null);
  const aRef = useRef(null);
  const bRef = useRef(null);
  const intervalRef = useRef(null);

  const rangePresets = weeksData?.range_presets || [];
  const weeks = weeksData?.weeks || [];
  const intervalDatePresets = weeksData?.interval_presets || [];

  const allRangePresets = [
    ...rangePresets,
    ...weeks.map((w) => ({
      key: w.key || w.week,
      label: w.label || w.week,
      from: w.start,
      to: w.end,
    })),
  ];

  const filteredSites = (window.WRM_API?.sites || []).filter((s) =>
    filters.entity === "all"
      ? true
      : String(s.entity_id) === String(filters.entity)
  );

  /* ================= RANGE (FIXED) ================= */

  const onFromChange = (value) => {
    setFilters((p) => ({
      ...p,
      range: {
        ...p.range,
        from: value,
        preset: "custom",
      },
    }));
  };

  const onToChange = (value) => {
    setFilters((p) => ({
      ...p,
      range: {
        ...p.range,
        to: value,
        preset: "custom",
      },
    }));
  };

  const applyRangePreset = (p) => {
    setFilters((prev) => ({
      ...prev,
      range: {
        ...prev.range,
        from: p.from,
        to: p.to,
        preset: p.key,
      },
    }));
  };

  /* ================= INTERVAL DATE ================= */

  const applyDatePreset = (side, p) => {
    setFilters((prev) => ({
      ...prev,
      [`interval_${side}`]: p.value,
      [`interval_${side}_preset`]: p.key,
    }));
  };

  const onDateChange = (side, value) => {
    setFilters((prev) => ({
      ...prev,
      [`interval_${side}`]: value,
      [`interval_${side}_preset`]: "custom",
    }));
  };

  /* ================= INTERVAL BUCKET ================= */

  const intervalBuckets = [
    { key: "5m", label: "5 Minutes", value: 5 },
    { key: "15m", label: "15 Minutes", value: 15 },
    { key: "30m", label: "30 Minutes", value: 30 },
    { key: "60m", label: "Hourly", value: 60 },
  ];

  /* ================= OUTSIDE CLICK ================= */

  useEffect(() => {
    const handler = (e) => {
      if (rangeRef.current && !rangeRef.current.contains(e.target))
        setOpenRange(false);

      if (aRef.current && !aRef.current.contains(e.target))
        setOpenA(false);

      if (bRef.current && !bRef.current.contains(e.target))
        setOpenB(false);

      if (intervalRef.current && !intervalRef.current.contains(e.target))
        setOpenInterval(false);
    };

    document.addEventListener("mousedown", handler);
    return () => document.removeEventListener("mousedown", handler);
  }, []);

  /* ================= LABEL ================= */

  const getIntervalLabel = () => {
    if (filters.interval_preset !== "custom") {
      return (
        intervalBuckets.find((i) => i.key === filters.interval_preset)
          ?.label || "Interval"
      );
    }

    const total = filters.interval || 5;
    const h = Math.floor(total / 60);
    const m = total % 60;

    return h > 0
      ? `${h}h ${m.toString().padStart(2, "0")}m`
      : `${m} Min`;
  };

  /* ================= UI ================= */

  return (
    <div className="filters">

      {/* ================= RANGE ================= */}
      {mode === "range" && (
        <div className="filters__group" ref={rangeRef}>
          <div className="filters__inputs">
            <input
              type="date"
              value={filters.range?.from || ""}
              onChange={(e) => onFromChange(e.target.value)}
            />
            <input
              type="date"
              value={filters.range?.to || ""}
              onChange={(e) => onToChange(e.target.value)}
            />
          </div>

          <button
            className="filters__toggle"
            onClick={() => setOpenRange((s) => !s)}
          >
            ☰
          </button>

          {openRange && (
            <div className="filters__dropdown">
              <div className="filters__section">
                <div className="filters__label">Presets</div>

                {allRangePresets.map((p) => (
                  <div
                    key={p.key}
                    className={`filters__option ${
                      filters.range?.preset === p.key ? "is-active" : ""
                    }`}
                    onClick={() => {
                      applyRangePreset(p);
                      setOpenRange(false);
                    }}
                  >
                    {p.label}
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}

      {/* ================= INTERVAL ================= */}
      {mode === "interval" && (
        <div className="filters__group filters__group--multi">

          {/* A */}
          <div className="filters__group" ref={aRef}>
            <div className="filters__inputs">
              <input
                type="date"
                value={filters.interval_a || ""}
                onChange={(e) => onDateChange("a", e.target.value)}
              />
            </div>

            <button
              className="filters__toggle"
              onClick={() => setOpenA((s) => !s)}
            >
              ☰
            </button>

            {openA && (
              <div className="filters__dropdown">
                <div className="filters__section">
                  <div className="filters__label">Base Date</div>
                  {intervalDatePresets.map((p) => (
                    <div
                      key={p.key}
                      className={`filters__option ${
                        filters.interval_a_preset === p.key ? "is-active" : ""
                      }`}
                      onClick={() => {
                        applyDatePreset("a", p);
                        setOpenA(false);
                      }}
                    >
                      {p.label}
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>

          {/* B */}
          <div className="filters__group" ref={bRef}>
            <div className="filters__inputs">
              <input
                type="date"
                value={filters.interval_b || ""}
                onChange={(e) => onDateChange("b", e.target.value)}
              />
            </div>

            <button
              className="filters__toggle"
              onClick={() => setOpenB((s) => !s)}
            >
              ☰
            </button>

            {openB && (
              <div className="filters__dropdown">
                <div className="filters__section">
                  <div className="filters__label">Compare Date</div>
                  {intervalDatePresets.map((p) => (
                    <div
                      key={p.key}
                      className={`filters__option ${
                        filters.interval_b_preset === p.key ? "is-active" : ""
                      }`}
                      onClick={() => {
                        applyDatePreset("b", p);
                        setOpenB(false);
                      }}
                    >
                      {p.label}
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>

          {/* INTERVAL */}
          <div className="filters__group" ref={intervalRef}>
            <button
              className="filters__interval-trigger"
              onClick={() => setOpenInterval((s) => !s)}
            >
              {getIntervalLabel()}
            </button>

            {openInterval && (
              <div className="filters__dropdown">
                <div className="filters__section">
                  <div className="filters__label">Interval</div>

                  {[
                    { label: "5 Minutes", value: 5 },
                    { label: "15 Minutes", value: 15 },
                    { label: "30 Minutes", value: 30 },
                    { label: "1 Hour", value: 60 },
                  ].map((p) => (
                    <div
                      key={p.value}
                      className="filters__option"
                      onClick={() => {
                        setFilters((prev) => ({
                          ...prev,
                          interval: p.value,
                          interval_value: p.value,
                          interval_unit: "minutes",
                          interval_preset: p.value === 60 ? "60m" : "custom",
                        }));

                        setOpenInterval(false);
                      }}
                    >
                      {p.label}
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>

        </div>
      )}

      {/* ENTITY */}
      <select
        value={filters.entity || "all"}
        onChange={(e) =>
          setFilters((p) => ({
            ...p,
            entity: e.target.value,
            site: "all",
          }))
        }
      >
        <option value="all">All Entities</option>
        {(window.WRM_API?.entities || []).map((e) => (
          <option key={e.id} value={e.id}>
            {e.name}
          </option>
        ))}
      </select>

      {/* SITE */}
      <select
        value={filters.site || "all"}
        onChange={(e) =>
          setFilters((p) => ({ ...p, site: e.target.value }))
        }
      >
        <option value="all">All Sites</option>
        {filteredSites.map((s) => (
          <option key={s.site_id} value={s.site_id}>
            {s.name}
          </option>
        ))}
      </select>

    </div>
  );
}

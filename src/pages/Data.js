import { useContext, useEffect, useState, useRef } from "@wordpress/element";
import { FilterContext } from "../contexts";
import axios from "axios";

export default function Data() {
  const { filters, setFilters } = useContext(FilterContext);
  const Interval = useRef(null);
  const AbortCtrl = useRef(null);

  const [status, setStatus] = useState("");
  const [progress, setProgress] = useState(0);
  const [refreshing, setRefreshing] = useState(false);
  const [currentJobId, setCurrentJobId] = useState(null);
  const [activeJobExists, setActiveJobExists] = useState(false);
  const [checkedActive, setCheckedActive] = useState(false);

  const api = window.WRM_API || {};

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

  /* ================= FIX: MATCH SALES PAGE ================= */
  const from = filters.range?.from || "";
  const to = filters.range?.to || "";

  /* ================= STATUS HELPERS ================= */

  const isActiveStatus = (status) =>
    ["pending", "running", "processing"].includes(status);

  const getStatusLabel = (status) => {
    switch (status) {
      case "pending":
        return "Queued...";
      case "running":
      case "processing":
        return "Processing...";
      case "completed":
        return "Completed";
      case "failed":
        return "Failed";
      default:
        return status || "";
    }
  };

  /* ================= RESET ================= */

  const resetJobState = (msg = "") => {
    setRefreshing(false);
    setActiveJobExists(false);
    setCurrentJobId(null);
    setProgress(0);
    setStatus(msg);
  };

  /* ================= CHECK ACTIVE ================= */

  const checkActiveJob = async () => {
    try {
      const res = await axios.get(`${api.url}fetch/active`, {
        headers: { "X-WP-Nonce": api.nonce },
      });

      const job = res.data;

      if (job && isActiveStatus(job.status)) {
        setRefreshing(true);
        setActiveJobExists(true);

        setStatus(getStatusLabel(job.status));
        setProgress(Number(job.progress ?? 0));
        setCurrentJobId(job.id);

        startPolling(job.id);
      } else {
        resetJobState();
      }
    } catch (err) {
      console.error(err);
      resetJobState();
    } finally {
      setCheckedActive(true);
    }
  };

  /* ================= POLLING ================= */

  const startPolling = (jobId) => {
    clearInterval(Interval.current);
    AbortCtrl.current?.abort();

    const controller = new AbortController();
    AbortCtrl.current = controller;

    Interval.current = setInterval(async () => {
      try {
        const res = await axios.get(`${api.url}fetch/${jobId}`, {
          headers: { "X-WP-Nonce": api.nonce },
          signal: controller.signal,
        });

        const job = res.data;
        const p = Number(job.progress ?? 0);

        setProgress(p);
        setStatus(getStatusLabel(job.status));

        if (p >= 100 || ["completed", "failed"].includes(job.status)) {
          clearInterval(Interval.current);
          resetJobState(
            job.status === "completed" ? "Refresh completed" : "Refresh failed",
          );
        }
      } catch (err) {
        if (err.name !== "CanceledError") console.error(err);
      }
    }, 4000);
  };

  /* ================= START ================= */

  const startRefresh = async () => {
    if (activeJobExists) return;

    if (!filters.entity || !from || !to) {
      alert("Please select entity and date range");
      return;
    }

    setRefreshing(true);
    setProgress(0);
    setStatus("Starting...");

    try {
      const res = await axios.post(
        `${api.url}fetch`,
        {
          entity: filters.entity,
          from,
          to,
        },
        {
          headers: { "X-WP-Nonce": api.nonce },
        },
      );

      if (!res.data.job_id) {
        setStatus("Failed to start job");
        setRefreshing(false);
        return;
      }

      setCurrentJobId(res.data.job_id);
      setActiveJobExists(true);

      startPolling(res.data.job_id);
    } catch (err) {
      console.error(err);
      setStatus("Error starting refresh");
      setRefreshing(false);
    }
  };

  /* ================= CANCEL ================= */

  const cancelRefresh = async () => {
    if (!currentJobId) return;

    try {
      await axios.post(
        `${api.url}fetch/${currentJobId}/cancel`,
        {},
        { headers: { "X-WP-Nonce": api.nonce } },
      );

      clearInterval(Interval.current);
      AbortCtrl.current?.abort();

      resetJobState("Refresh cancelled");
    } catch (err) {
      console.error(err);
      setStatus("Error cancelling refresh");
    }
  };

  /* ================= EFFECTS ================= */

  useEffect(() => {
    checkActiveJob();

    return () => {
      clearInterval(Interval.current);
      AbortCtrl.current?.abort();
    };
  }, []);

  /* 🔥 FIX: re-check when filters change */
  useEffect(() => {
    resetJobState();
    checkActiveJob();
  }, [filters.entity, from, to]);

  /* ================= LOADING ================= */

  if (!checkedActive) {
    return (
      <div className="sales">
        <div className="table-card skeleton-table" />
      </div>
    );
  }

  /* ================= UI ================= */

  return (
    <div className="sales">
      <div className="header-bar">
        <h1>Data Refresh</h1>
      </div>

      <div className="table-card">
        <h2>Refresh Data</h2>

        <div className="export-buttons">
          {!activeJobExists && (
            <button className="btn btn-primary" onClick={startRefresh}>
              {refreshing ? "Running..." : "Start Refresh"}
            </button>
          )}

          {(refreshing || currentJobId) && (
            <button className="btn btn-secondary" onClick={cancelRefresh}>
              Cancel
            </button>
          )}
        </div>

        {(refreshing || currentJobId) && (
          <>
            <div className="progress-bar">
              <div className="progress-fill" style={{ width: `${progress}%` }}>
                {progress}%
              </div>
            </div>

            <div className="status-text">{status}</div>
          </>
        )}
      </div>
    </div>
  );
}

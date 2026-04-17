import { useContext, useEffect, useState, useRef } from "@wordpress/element";
import { FilterContext } from "../contexts";
import axios from "axios";

export default function Data() {
  const { filters } = useContext(FilterContext);

  const Interval = useRef(null);
  const AbortCtrl = useRef(null);

  const [status, setStatus] = useState("");
  const [progress, setProgress] = useState(0);
  const [refreshing, setRefreshing] = useState(false);
  const [currentJobId, setCurrentJobId] = useState(null);
  const [activeJobExists, setActiveJobExists] = useState(false);
  const [activeEntityId, setActiveEntityId] = useState(null);
  const [checkedActive, setCheckedActive] = useState(false);

  const api = window.WRM_API || {};

  /* =========================
     STATUS HELPERS
  ========================= */

  const isActiveStatus = (status) =>
    ["pending", "running", "processing"].includes(status);

  const getStatusLabel = (status) => {
    switch (status) {
      case "pending":
        return "Queued...";
      case "running":
        return "Processing...";
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

  /* =========================
     CHECK ACTIVE JOB
  ========================= */

  const checkActiveJob = async () => {
    try {
      const res = await axios.get(`${api.url}fetch/active`, {
        headers: { "X-WP-Nonce": api.nonce },
      });

      const job = res.data;

      if (job && isActiveStatus(job.status)) {
        setRefreshing(true);
        setActiveJobExists(true);
        setActiveEntityId(job.entity_id || null);

        setStatus(getStatusLabel(job.status));
        setProgress(Number(job.progress ?? 0));
        setCurrentJobId(job.id);

        startProgressPolling(job.id);
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

  /* =========================
     POLLING
  ========================= */

  const startProgressPolling = (jobId) => {
    if (Interval.current) clearInterval(Interval.current);
    if (AbortCtrl.current) AbortCtrl.current.abort();

    const controller = new AbortController();
    AbortCtrl.current = controller;

    Interval.current = setInterval(async () => {
      try {
        const job = await axios.get(`${api.url}fetch/${jobId}`, {
          headers: { "X-WP-Nonce": api.nonce },
          signal: controller.signal,
        });

        const p = Number(job.data.progress ?? 0);

        setProgress(p);
        setStatus(getStatusLabel(job.data.status));

        if (p >= 100 || job.data.status === "completed" || job.data.status === "failed") {
          clearInterval(Interval.current);
          resetJobState(
            job.data.status === "completed"
              ? "Refresh completed"
              : "Refresh stopped"
          );
        }
      } catch (err) {
        if (err.name !== "CanceledError") console.error(err);
      }
    }, 4000);
  };

  /* =========================
     START REFRESH
  ========================= */

  const startRefresh = async () => {
    if (activeJobExists) return;

    if (!filters.entity || !filters.from || !filters.to) {
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
          from: filters.from,
          to: filters.to,
        },
        {
          headers: { "X-WP-Nonce": api.nonce },
        }
      );

      if (!res.data.job_id) {
        setStatus("Failed to start job");
        setRefreshing(false);
        return;
      }

      setCurrentJobId(res.data.job_id);
      setActiveJobExists(true);
      setActiveEntityId(filters.entity);

      startProgressPolling(res.data.job_id);
    } catch (err) {
      console.error(err);
      setStatus("Error starting refresh");
      setRefreshing(false);
    }
  };

  /* =========================
     CANCEL
  ========================= */

  const cancelRefresh = async () => {
    if (!currentJobId) return;

    try {
      await axios.post(
        `${api.url}fetch/${currentJobId}/cancel`,
        {},
        { headers: { "X-WP-Nonce": api.nonce } }
      );

      clearInterval(Interval.current);
      if (AbortCtrl.current) AbortCtrl.current.abort();

      resetJobState("Refresh cancelled");
    } catch (err) {
      console.error(err);
      setStatus("Error cancelling refresh");
    }
  };

  /* =========================
     RESET
  ========================= */

  const resetJobState = (msg = "") => {
    setRefreshing(false);
    setActiveJobExists(false);
    setActiveEntityId(null);
    setCurrentJobId(null);
    setProgress(0);
    setStatus(msg);
  };

  useEffect(() => {
    checkActiveJob();

    return () => {
      clearInterval(Interval.current);
      if (AbortCtrl.current) AbortCtrl.current.abort();
    };
  }, []);

  /* =========================
     LOADING STATE
  ========================= */

  if (!checkedActive) {
    return (
      <div className="wrm-content">
        <div className="table-card skeleton-table" />
      </div>
    );
  }

  /* =========================
     RENDER
  ========================= */

  return (
    <div className="wrm-content">

      {/* HEADER */}
      <div className="header-bar">
        <h1 className="page-title">Report Manager</h1>
      </div>

      {/* ACTION CARD */}
      <div className="table-card">

        <h2>Data Refresh</h2>

        <div className="export-buttons" style={{ marginBottom: "10px" }}>
          {!activeJobExists && (
            <button className="wrm-btn wrm-btn-primary" onClick={startRefresh}>
              {refreshing ? "Running..." : "Start Refresh"}
            </button>
          )}

          {(refreshing || currentJobId) && (
            <button className="wrm-btn" onClick={cancelRefresh}>
              Cancel
            </button>
          )}
        </div>

        {/* ACTIVE JOB INFO */}
        {activeJobExists && activeEntityId && (
          <div style={{ fontSize: "12px", color: "#b91c1c", marginBottom: "8px" }}>
            Active entity ID: {activeEntityId}
          </div>
        )}

        {/* PROGRESS BAR */}
        {(refreshing || currentJobId) && (
          <div
            style={{
              width: "100%",
              background: "#e5e7eb",
              borderRadius: "6px",
              overflow: "hidden",
              height: "22px",
            }}
          >
            <div
              style={{
                width: `${progress}%`,
                background: "#2563eb",
                height: "100%",
                color: "#fff",
                fontSize: "12px",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                transition: "width 0.3s ease",
              }}
            >
              {progress}%
            </div>
          </div>
        )}

        {/* STATUS */}
        {(refreshing || currentJobId) && (
          <div style={{ marginTop: "6px", fontSize: "12px", color: "#6b7280" }}>
            {status}
          </div>
        )}

      </div>
    </div>
  );
}

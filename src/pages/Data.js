import { useContext, useEffect, useState, useRef } from "@wordpress/element";
import { FilterContext } from "../App";
import axios from "axios";

export default function Data() {
  const { filters } = useContext(FilterContext); // get entity, from, to
  const kurveInterval = useRef(null);

  const [kurveStatus, setKurveStatus] = useState("");
  const [kurveProgress, setKurveProgress] = useState(0);
  const [refreshing, setRefreshing] = useState(false);

  const [tbFile, setTbFile] = useState(null);
  const [tbStatus, setTbStatus] = useState("");
  const [currentJobId, setCurrentJobId] = useState(null);

  const api = window.WRM_API || {};

  // -------------------------
  // Check active Kurve job
  // -------------------------
  const checkActiveJob = async () => {
    if (!filters.entity || !filters.from || !filters.to) return;
    try {
      const res = await axios.get(`${api.url}fetch/active`, {
        headers: { "X-WP-Nonce": api.nonce },
      });
      if (res.data.status && res.data.status !== "idle") {
        setRefreshing(true);
        setKurveStatus("Kurve refresh running...");
        setKurveProgress(res.data.progress || 0);
        setCurrentJobId(res.data.id);
        startProgressPolling(res.data.id);
      } else {
        setRefreshing(false);
        setKurveStatus("");
        setKurveProgress(0);
        setCurrentJobId(null);
      }
    } catch {
      console.log("Error checking active Kurve job");
    }
  };

  // -------------------------
  // Poll progress
  // -------------------------
  const startProgressPolling = (jobId) => {
    if (kurveInterval.current) clearInterval(kurveInterval.current);
    kurveInterval.current = setInterval(async () => {
      try {
        const job = await axios.get(`${api.url}fetch/${jobId}`, {
          headers: { "X-WP-Nonce": api.nonce },
        });
        const perc = job.data.progress || 0;
        setKurveProgress(perc);
        if (perc >= 100 || job.data.status !== "running") {
          clearInterval(kurveInterval.current);
          setRefreshing(false);
          setKurveStatus("Kurve refresh completed");
          setCurrentJobId(null);
        }
      } catch {
        console.log("Error fetching Kurve progress");
      }
    }, 5000);
  };

  // -------------------------
  // Start Kurve refresh
  // -------------------------
  const startKurveRefresh = async () => {
    if (!filters.entity || !filters.from || !filters.to)
      return alert("Please select entity and date range");

    setRefreshing(true);
    setKurveStatus("Starting Kurve refresh...");
    setKurveProgress(0);

    try {
      const res = await axios.post(
        `${api.url}fetch`,
        { entity: filters.entity, from: filters.from, to: filters.to },
        { headers: { "X-WP-Nonce": api.nonce } }
      );
      if (!res.data.job_id) {
        setKurveStatus("Failed to start job");
        setRefreshing(false);
        return;
      }
      setKurveStatus("Kurve refresh started");
      setCurrentJobId(res.data.job_id);
      startProgressPolling(res.data.job_id);
    } catch {
      setKurveStatus("Error starting Kurve refresh");
      setRefreshing(false);
    }
  };

  // -------------------------
  // Cancel Kurve refresh
  // -------------------------
  const cancelKurveRefresh = async () => {
    if (!currentJobId) return;
    try {
      await axios.post(`${api.url}fetch/${currentJobId}/cancel`, {}, {
        headers: { "X-WP-Nonce": api.nonce }
      });
      clearInterval(kurveInterval.current);
      setRefreshing(false);
      setKurveProgress(0);
      setKurveStatus("Kurve refresh cancelled");
      setCurrentJobId(null);
    } catch {
      setKurveStatus("Error cancelling Kurve refresh");
    }
  };

  // -------------------------
  // TouchBistro upload
  // -------------------------
  const uploadTB = async () => {
    if (!tbFile) return alert("Select a file");
    const formData = new FormData();
    formData.append("file", tbFile);
    setTbStatus("Uploading TouchBistro file...");

    try {
      const res = await axios.post(`${api.url}import/touchbistro`, formData, {
        headers: { "X-WP-Nonce": api.nonce }
      });
      if (res.data.status === "success") {
        setTbStatus(`Inserted: ${res.data.inserted}, Skipped duplicates: ${res.data.skipped}`);
      } else {
        setTbStatus(res.data.message || "Import failed");
      }
    } catch {
      setTbStatus("Error uploading TB file");
    }
  };

  useEffect(() => {
    checkActiveJob();
    return () => clearInterval(kurveInterval.current);
  }, [filters.entity, filters.from, filters.to]);

  return (
    <div className="wrm-wrap">
      <h1>Report Manager</h1>

      {/* Kurve Refresh */}
      <div className="wrm-filters">
        <h3>Kurve Refresh</h3>
        <button onClick={startKurveRefresh} disabled={refreshing}>
          {refreshing ? "Refreshing..." : "Start Kurve Refresh"}
        </button>
        {refreshing && (
          <button style={{ marginLeft: "5px" }} onClick={cancelKurveRefresh}>
            Cancel
          </button>
        )}
        {refreshing && (
          <div style={{ marginTop: "10px", width: "100%", background: "#eee" }}>
            <div
              style={{
                width: `${kurveProgress}%`,
                height: "24px",
                background: "#4CAF50",
                textAlign: "center",
                color: "white",
                lineHeight: "24px",
              }}
            >
              {kurveProgress}%
            </div>
            <div>{kurveStatus}</div>
          </div>
        )}
      </div>

      {/* TouchBistro Upload */}
      <div className="wrm-filters" style={{ marginTop: "20px" }}>
        <h3>TouchBistro Upload</h3>
        <input
          type="file"
          onChange={(e) => setTbFile(e.target.files[0])}
          accept=".xlsx,.csv"
        />
        <button onClick={uploadTB}>Upload TB</button>
        <div>{tbStatus}</div>
      </div>
    </div>
  );
}

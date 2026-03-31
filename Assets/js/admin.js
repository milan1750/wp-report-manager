(function ($) {
  $(document).ready(function () {
    const api = window.WRM_API || {};
    const money = (v) => Number(v || 0).toFixed(2);
    let currentPage = 1,
        kurveInterval = null;

    const $refreshBtn = $("#wrm-refresh-kurve");
    const $status = $("#wrm-refresh-status");
    const $progressContainer = $("#wrm-progress-container");
    const $progressBar = $("#wrm-progress-bar");
		    // Cache selects
    const $entitySelect = $("#wrm-entity"); // rename your entity select for clarity
    const $siteSelect   = $("#wrm-site");

    // When entity changes, filter sites
    $entitySelect.on("change", function () {
      const selectedEntity = $(this).val();

      $siteSelect.find("option").each(function () {
        const $opt = $(this);
        const entityId = $opt.data("entity-id");

        // Keep default "Select Site" option
        if ($opt.val() === "") return $opt.show();

        // Show only sites belonging to the selected entity
        if (entityId == selectedEntity || selectedEntity === "") {
          $opt.show();
        } else {
          $opt.hide();
        }
      });

      // Reset site select value
      $siteSelect.val("");
    });

    // Trigger change on page load to filter sites if an entity is preselected
    $entitySelect.trigger("change");

    // ---------------------
    // Tabs
    // ---------------------
    $(".nav-tab").on("click", function (e) {
      e.preventDefault();
      $(".nav-tab").removeClass("nav-tab-active");
      $(this).addClass("nav-tab-active");
      $(".wrm-tab-content").hide();
      $($(this).attr("href")).show();
    });

    // ---------------------
    // Load Raw Data
    // ---------------------
    function loadData(page = 1) {
      const entity = $("#wrm-entity").val(),
				site = $("#wrm-site").val(),
            from = $("#wrm-from").val(),
            to   = $("#wrm-to").val();

      if (!from || !to) return alert("Select both dates");

      let url = `${api.url}data?from=${from}&to=${to}&page=${page}`;
      if (entity) url += `&entity=${entity}`;
      if (site) url += `&site=${site}`;

      $("#wrm-data-table tbody").html('<tr><td colspan="9">Loading...</td></tr>');
      $("#wrm-pagination").html("");

      $.ajax({
        url: url,
        headers: { "X-WP-Nonce": api.nonce },
        method: "GET",
        dataType: "json",
        success: function (res) {
          const rows = res.data || [];
          $("#wrm-data-table tbody").html(
            rows.map(r => `
              <tr>
                <td>${r.id}</td>
                <td>${r.transaction_id}</td>
                <td>${r.site_name}</td>
                <td>${r.complete_datetime}</td>
                <td>${money(r.subtotal)}</td>
                <td>${money(r.discounts)}</td>
                <td>${money(r.tax)}</td>
                <td>${money(r.total)}</td>
              </tr>
            `).join('') || '<tr><td colspan="9">No data</td></tr>'
          );

          // Pagination
          const pagination = res.pagination || {};
          currentPage = pagination.current || 1;
          let pagHtml = "";
          if (currentPage > 1) pagHtml += `<button class="wrm-page-btn" data-page="${currentPage-1}">Prev</button>`;
          if (currentPage < (pagination.total_pages || 1)) pagHtml += `<button class="wrm-page-btn" data-page="${currentPage+1}">Next</button>`;
          $("#wrm-pagination").html(pagHtml);
        },
        error: () => $("#wrm-data-table tbody").html('<tr><td colspan="9">Error loading data</td></tr>')
      });
    }

    $("#wrm-load").on("click", () => loadData(1));
    $(document).on("click", ".wrm-page-btn", function () {
      const page = parseInt($(this).data("page"));
      if (page) loadData(page);
    });

    // ---------------------
    // Kurve Refresh Enhancements
    // ---------------------
    function checkActiveJob() {
  $.ajax({
    url: `${api.url}fetch/active`,
    headers: { "X-WP-Nonce": api.nonce },
    method: "GET",
    dataType: "json",
    success: function (res) {
      if (res.status && res.status !== "idle") {
        const jobId = res.id; // <-- use this ID!
        $refreshBtn.prop("disabled", true).text("Kurve Refresh Running...");

        if (!$("#wrm-cancel-kurve").length) {
          $refreshBtn.after(
            `<button id="wrm-cancel-kurve" class="button button-secondary" style="margin-left:5px;">Cancel</button>`
          );

          $("#wrm-cancel-kurve").on("click", function () {
            cancelJob(jobId);
          });
        }

        $progressContainer.show();
      } else {
        $refreshBtn.prop("disabled", false).text("Refresh Kurve");
        $("#wrm-cancel-kurve").remove();
        $progressContainer.hide();
      }
    },
    error: () => console.log("Error checking active Kurve job")
  });
}

    function cancelJob(jobId) {
  if (!jobId) return alert("No valid job ID to cancel");

  $.ajax({
    url: `${api.url}fetch/${jobId}/cancel`,
    headers: { "X-WP-Nonce": api.nonce },
    method: "POST",
    dataType: "json",
    success: function () {
      $status.text("Kurve refresh cancelled");
      checkActiveJob();
      if (kurveInterval) clearInterval(kurveInterval);
    },
    error: () => $status.text("Error cancelling job")
  });
}

    $refreshBtn.on("click", function () {
      const entity = $("#wrm-refresh-entity").val(),
            from   = $("#wrm-refresh-from").val(),
            to     = $("#wrm-refresh-to").val();

      if (!entity || !from || !to) return alert("Select entity and dates");

      $refreshBtn.prop("disabled", true).text("Starting...");
      $status.text("Starting Kurve refresh...");
      $progressBar.css("width", "0%").text("0%");
      $progressContainer.show();

      $.ajax({
        url: `${api.url}fetch`,
        headers: { "X-WP-Nonce": api.nonce },
        method: "POST",
        data: { entity, from, to },
        dataType: "json",
        success: function (res) {
          if (!res.job_id) return $status.text("Failed to start job");

          $status.text("Kurve refresh started");

          // Show cancel button
          if (!$("#wrm-cancel-kurve").length) {
            $refreshBtn.after(
              `<button id="wrm-cancel-kurve" class="button button-secondary" style="margin-left:5px;">Cancel</button>`
            );
            $("#wrm-cancel-kurve").on("click", cancelJob.bind(null, res.job_id));
          }

          kurveInterval = setInterval(() => {
            $.ajax({
              url: `${api.url}fetch/${res.job_id}`,
              headers: { "X-WP-Nonce": api.nonce },
              method: "GET",
              dataType: "json",
              success: function (job) {
                const perc = job.progress || 0;
                $progressBar.css("width", perc + "%").text(perc + "%");

                if (perc >= 100 || job.status !== "running") {
                  clearInterval(kurveInterval);
                  $status.text("Kurve refresh completed");
                  $progressBar.css("width", "100%").text("100%");
                  checkActiveJob();
                }
              },
              error: () => console.log("Error fetching Kurve job progress")
            });
          }, 10000);
        },
        error: () => {
          $status.text("Error starting Kurve refresh");
          $refreshBtn.prop("disabled", false).text("Refresh Kurve");
        }
      });
    });

    // Initial check on page load
    checkActiveJob();

    // ---------------------
    // Upload TouchBistro (unchanged)
    // ---------------------
    $("#wrm-upload-tb").on("click", function () {
      const fileInput = $("#wrm-tb-file")[0],

			status = $("#wrm-refresh-status");

      if (!fileInput.files.length) return alert("Select a file");

      const formData = new FormData();
      formData.append("file", fileInput.files[0]);

      status.text("Uploading TouchBistro file...");

      $.ajax({
        url: `${api.url}import/touchbistro`,
        headers: { "X-WP-Nonce": api.nonce },
        method: "POST",
        data: formData,
        processData: false,
        contentType: false,
        dataType: "json",
        success: function (res) {
          if (res.status === "success") {
            status.html(`
              <strong>Import Finished</strong><br>
              Inserted: ${res.inserted}<br>
              Skipped duplicates: ${res.skipped}
            `);
          } else {
            status.text(res.message || "Import failed");
          }
        },
        error: function (xhr, textStatus, errorThrown) {
          console.error(xhr, textStatus, errorThrown);
          status.text("Error uploading TB file");
        }
      });
    });

  });
})(jQuery);

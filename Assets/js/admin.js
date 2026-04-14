(function ($) {
  $(document).ready(function () {

    let map;
    let markers = [];
    let infoWindow;

    const locations = [
      { name: "Covent Garden Store", lat: 51.5117, lng: -0.1240, address: "Covent Garden", phone: "+44 20 7123 4567", infoLink: "https://example.com/covent" },
      { name: "Oxford Street Store", lat: 51.5154, lng: -0.1410, address: "Oxford Street", phone: "+44 20 7234 5678", infoLink: "https://example.com/oxford" },
      { name: "King’s Cross Store", lat: 51.5308, lng: -0.1238, address: "Kings Cross", phone: "+44 20 7345 6789", infoLink: "https://example.com/kings" },
      { name: "Canary Wharf Store", lat: 51.5054, lng: -0.0235, address: "Canary Wharf", phone: "+44 20 7456 7890", infoLink: "https://example.com/canary" },
      { name: "Soho Store", lat: 51.5136, lng: -0.1365, address: "Soho", phone: "+44 20 7567 8901", infoLink: "https://example.com/soho" }
    ];

    const iconUrl = "/wp-content/plugins/store-locator/assets/pointer.png";

    function initMap() {

      map = new google.maps.Map(document.getElementById("map"), {

        center: { lat: 51.5074, lng: -0.1278 },

        // ✅ DEFAULT ZOOM FIX
        zoom: 9,

        disableDefaultUI: true,

        styles: [
          { elementType: "geometry", stylers: [{ color: "#f5f5f5" }] },
          { elementType: "labels.icon", stylers: [{ visibility: "off" }] },
          { elementType: "labels.text.fill", stylers: [{ color: "#7a7a7a" }] },
          { featureType: "road", elementType: "geometry", stylers: [{ color: "#ffffff" }] },
          { featureType: "water", elementType: "geometry", stylers: [{ color: "#d9e6ef" }] },
          { featureType: "poi", stylers: [{ visibility: "off" }] },
          { featureType: "transit", stylers: [{ visibility: "off" }] }
        ]
      });

      infoWindow = new google.maps.InfoWindow();

      locations.forEach((loc) => {

        const marker = new google.maps.Marker({
          position: { lat: loc.lat, lng: loc.lng },
          map: map,
          title: loc.name,
          icon: {
            url: iconUrl,
            scaledSize: new google.maps.Size(60, 60)
          }
        });

        const content = `
<div style="font-family:Arial;width:240px;overflow:hidden;position:relative;">

  <button id="closeBtn" style="
    position:absolute;
    top:-8px;
    right:-8px;
    width:28px;
    height:28px;
    border-radius:50%;
    border:none;
    background:#fff;
    box-shadow:0 2px 6px rgba(0,0,0,0.2);
    cursor:pointer;">×</button>

  <h3 style="margin:0 0 6px 0;font-size:15px;">
    ${loc.name}
  </h3>

  <p style="margin:0;font-size:12px;"><b>Location:</b> ${loc.address}</p>
  <p style="margin:4px 0 10px 0;font-size:12px;"><b>Tel:</b> ${loc.phone}</p>

  <hr style="border:none;border-top:1px solid #eee;margin:8px 0;">

  <p style="margin:0 0 6px 0;font-size:12px;"><b>Order on:</b></p>

  <div style="display:flex;gap:10px;margin-bottom:10px;">
    <a href="https://www.ubereats.com" target="_blank">
      <img src="/wp-content/plugins/store-locator/assets/ubereats.png" style="height:24px;">
    </a>

    <a href="https://deliveroo.co.uk" target="_blank">
      <img src="/wp-content/plugins/store-locator/assets/delivero.png" style="height:24px;">
    </a>
  </div>

  <button onclick="window.open('${loc.infoLink}','_blank')"
    style="width:100%;background:#111;color:#fff;border:none;padding:8px;border-radius:6px;">
    Store Information
  </button>

</div>
`;

        marker.addListener("click", () => {

          infoWindow.close();
          infoWindow.setContent(content);
          infoWindow.open(map, marker);

          // ✅ SAFE MOVE (no jump bug)
          map.panTo(marker.getPosition());

          setTimeout(() => {
            map.setZoom(9);
          }, 150);

        });

        markers.push({
          name: loc.name.toLowerCase(),
          marker: marker
        });

      });

      // close popup on map click
      map.addListener("click", () => infoWindow.close());

      // close button
      $(document).on("click", "#closeBtn", function () {
        infoWindow.close();
      });

      // SEARCH FIX (safe reset included)
      $("#searchBox").on("keyup", function () {

        const value = $(this).val().toLowerCase().trim();

        let found = null;

        if (value === "") {

          markers.forEach(item => item.marker.setMap(map));

          map.setCenter({ lat: 51.5074, lng: -0.1278 });
          map.setZoom(9);
          infoWindow.close();

          return;
        }

        markers.forEach(item => {

          const match = item.name.includes(value);

          if (match) {
            item.marker.setMap(map);
            found = item.marker;
          } else {
            item.marker.setMap(null);
          }

        });

        if (found) {

          map.panTo(found.getPosition());

          setTimeout(() => {
            map.setZoom(9);
          }, 150);

          google.maps.event.trigger(found, "click");
        }

      });

    }

    // expose initMap globally
    window.initMap = initMap;

  });
})(jQuery);

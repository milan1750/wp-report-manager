import { createElement } from "@wordpress/element";
import { createRoot } from "react-dom/client";
import App from "./App";
import "../assets/css/style.scss";

const root = document.getElementById("wrm-root");

if (root) {
  const reactRoot = createRoot(root); // <-- createRoot for React 18
  reactRoot.render(<App />);
}

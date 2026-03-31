import { render } from "@wordpress/element";
import App from "./App";
import "../assets/css/style.scss";

const root = document.getElementById("wrm-root");

if (root) {
  render(<App />, root);
}

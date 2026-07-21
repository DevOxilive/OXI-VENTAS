import axios from "axios";
import { initializeRealtime } from "./realtime";
window.axios = axios;

window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

initializeRealtime();

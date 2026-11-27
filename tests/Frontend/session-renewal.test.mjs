import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import vm from "node:vm";
import { parse } from "@babel/parser";
import { parse as parseVue } from "@vue/compiler-sfc";
import { ref } from "vue";

const file = new URL("../../resources/js/Layouts/AdminLayout.vue", import.meta.url);
const source = parseVue(readFileSync(file, "utf8")).descriptor.scriptSetup.content;
const handler = parse(source, { sourceType: "module" }).program.body
    .find(node => node.id?.name === "continueRoleSession");

test("un fallo de red permite reintentar sin ampliar ni cerrar la sesión", async () => {
    let logout = 0;
    const renewals = [];
    const state = {
        continuingRoleSession: ref(false), sessionRenewalError: ref(""),
        showSessionExpiryWarning: ref(true), sessionLogoutStarted: false,
        closeExpiredSession: () => logout++, scheduleRoleSessionTimeout: expiry => renewals.push(expiry),
        route: () => "/sesion/continuar", window: { axios: { post: () => Promise.reject(new Error("offline")) } },
    };
    const confirm = vm.runInNewContext(source.slice(handler.start, handler.end) + "\ncontinueRoleSession", state);
    await confirm();
    assert.equal(logout, 0);
    assert.deepEqual(renewals, []);
    assert.equal(state.continuingRoleSession.value, false);
    assert.equal(state.showSessionExpiryWarning.value, true);
    assert.match(state.sessionRenewalError.value, /Intenta de nuevo/);
    state.window.axios.post = async () => ({ data: { expires_at: 12345 } });
    await confirm();
    assert.deepEqual(renewals, [12345]);
    state.window.axios.post = () => Promise.reject({ response: { status: 401 } });
    await confirm();
    assert.equal(logout, 1);
});

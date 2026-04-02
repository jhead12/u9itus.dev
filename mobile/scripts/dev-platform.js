#!/usr/bin/env node

const net = require("net");
const http = require("http");
const { spawn } = require("child_process");

const METRO_PORT = Number(process.env.METRO_PORT || 8081);

function printUsage() {
    console.log("Usage: npm run dev -- <ios|android> [react-native-run-args]");
    console.log("Examples:");
    console.log('  npm run dev -- ios --simulator "iPhone 16 Pro"');
    console.log("  npm run dev -- android --deviceId emulator-5554");
    console.log("  npm run dev:ios");
    console.log("  npm run dev:android");
}

function isPortOpen(port, host = "127.0.0.1", timeoutMs = 700) {
    return new Promise((resolve) => {
        const socket = new net.Socket();

        const finish = (open) => {
            socket.removeAllListeners();
            socket.destroy();
            resolve(open);
        };

        socket.setTimeout(timeoutMs);
        socket.once("connect", () => finish(true));
        socket.once("timeout", () => finish(false));
        socket.once("error", () => finish(false));
        socket.connect(port, host);
    });
}

async function waitForPort(port, retries = 45, delayMs = 500) {
    for (let i = 0; i < retries; i++) {
        // eslint-disable-next-line no-await-in-loop
        if (await isPortOpen(port)) return true;
        // eslint-disable-next-line no-await-in-loop
        await new Promise((r) => setTimeout(r, delayMs));
    }
    return false;
}

function isMetroStatusHealthy(port, timeoutMs = 900) {
    return new Promise((resolve) => {
        const request = http.get(
            {
                hostname: "127.0.0.1",
                port,
                path: "/status",
                timeout: timeoutMs,
            },
            (response) => {
                let body = "";
                response.setEncoding("utf8");
                response.on("data", (chunk) => {
                    body += chunk;
                });
                response.on("end", () => {
                    resolve(
                        response.statusCode === 200 &&
                            body.trim() === "packager-status:running",
                    );
                });
            },
        );

        request.on("timeout", () => {
            request.destroy();
            resolve(false);
        });

        request.on("error", () => resolve(false));
    });
}

async function isMetroRunning(port) {
    if (!(await isPortOpen(port))) return false;
    return isMetroStatusHealthy(port);
}

async function waitForMetro(port, retries = 45, delayMs = 500) {
    for (let i = 0; i < retries; i++) {
        // eslint-disable-next-line no-await-in-loop
        if (await isMetroRunning(port)) return true;
        // eslint-disable-next-line no-await-in-loop
        await new Promise((r) => setTimeout(r, delayMs));
    }
    return false;
}

function spawnCommand(command, args, options = {}) {
    return spawn(command, args, {
        stdio: "inherit",
        shell: process.platform === "win32",
        ...options,
    });
}

async function main() {
    const argv = process.argv.slice(2);
    const platform = argv[0];
    const passthroughArgs = argv.slice(1);

    if (!platform || ["ios", "android"].indexOf(platform) === -1) {
        printUsage();
        process.exitCode = 1;
        return;
    }

    let metroStartedByScript = false;
    let metroProc = null;

    const metroAlreadyRunning = await isMetroRunning(METRO_PORT);

    if (!metroAlreadyRunning) {
        metroStartedByScript = true;
        console.log("[dev] Metro not detected. Starting Metro...");
        metroProc = spawnCommand("npx", ["react-native", "start"]);

        const metroReady = await waitForMetro(METRO_PORT);
        if (!metroReady) {
            console.error(
                `[dev] Metro did not become ready on port ${METRO_PORT}.`,
            );
            console.error(
                "[dev] If another process is occupying 8081, stop it and retry.",
            );
            if (metroProc) metroProc.kill("SIGTERM");
            process.exitCode = 1;
            return;
        }
    } else {
        console.log("[dev] Reusing running Metro instance.");
    }

    const runArgs = [
        "react-native",
        platform === "ios" ? "run-ios" : "run-android",
        "--no-packager",
        ...passthroughArgs,
    ];

    const hasSimulatorFlag = passthroughArgs.includes("--simulator");
    const defaultIosSimulator = process.env.IOS_SIMULATOR || "iPhone 16 Pro";
    if (platform === "ios" && !hasSimulatorFlag) {
        runArgs.push("--simulator", defaultIosSimulator);
    }

    console.log(`[dev] Launching ${platform}...`);
    const runProc = spawnCommand("npx", runArgs);

    const shutdown = () => {
        if (metroStartedByScript && metroProc && !metroProc.killed) {
            metroProc.kill("SIGTERM");
        }
    };

    process.on("SIGINT", () => {
        shutdown();
        process.exit(130);
    });

    process.on("SIGTERM", () => {
        shutdown();
        process.exit(143);
    });

    runProc.on("exit", (code) => {
        if (code !== 0) {
            shutdown();
            process.exit(code || 1);
            return;
        }

        if (!metroStartedByScript) {
            process.exit(0);
            return;
        }

        console.log("[dev] App launched. Metro is running for hot reload.");
        console.log("[dev] Press Ctrl+C to stop Metro.");
    });
}

main().catch((error) => {
    console.error("[dev] Unexpected error:", error);
    process.exit(1);
});

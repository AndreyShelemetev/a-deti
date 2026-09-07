/**
 * pm2 process file: the site itself and the updater service.
 *   pm2 start ecosystem.config.cjs && pm2 save && pm2 startup
 * Both processes read the repository's .env (Next.js does it natively, the updater via process.loadEnvFile).
 */
const path = require("node:path");
const app = process.env.PM2_APP || "a-deti";
const repo = __dirname;

module.exports = {
  apps: [
    {
      name: app,
      cwd: repo,
      script: "node_modules/next/dist/bin/next",
      args: `start -p ${process.env.PORT || 3000}`,
      exec_mode: "fork",
      instances: 1,
      autorestart: true,
      max_memory_restart: "700M",
      env: { NODE_ENV: "production", PORT: process.env.PORT || 3000 },
      out_file: path.join(repo, "logs", "web.log"),
      error_file: path.join(repo, "logs", "web.error.log"),
      merge_logs: true,
      time: true,
    },
    {
      name: `${app}-updater`,
      cwd: repo,
      script: "updater/server.mjs",
      autorestart: true,
      env: { NODE_ENV: "production", HOST: "127.0.0.1", PORT: process.env.UPDATER_PORT || 4100, REPO_DIR: repo, DEPLOY_MODE: "pm2", PM2_APP: app },
      out_file: path.join(repo, "logs", "updater.log"),
      error_file: path.join(repo, "logs", "updater.error.log"),
      merge_logs: true,
      time: true,
    },
  ],
};

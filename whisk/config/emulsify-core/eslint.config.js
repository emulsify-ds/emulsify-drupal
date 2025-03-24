import { defineConfig } from "eslint/config";
import {default as emulsifyCoreConfig} from "../../node_modules/@emulsify/core/config/eslint.config.js";

export default defineConfig(

    // apply an array config to a subset of files
    {
        files: ["../../src/**/*.js"],
        extends: [emulsifyCoreConfig]
    },

    // your modifications
    {
        rules: {
            "no-unused-vars": "warn"
        }
    }
);

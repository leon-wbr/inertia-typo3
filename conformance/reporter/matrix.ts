import { appendFileSync, writeFileSync } from "node:fs";
import { resolve } from "node:path";

import type { Reporter, TestCase, TestResult } from "@playwright/test/reporter";

import { CASES } from "./cases";

/**
 * Writes the conformance matrix: one row per case of the plan
 * (docs/plans/2026-11-05-compat-and-native.md), one column per project.
 *
 * - pass: every test of the case passed;
 * - fail: a test failed, or a test.fail() passed (fixed, remove the marker);
 * - missing: a known gap, a test.fail() that failed as expected;
 * - n/a: skipped for this project;
 * - (empty): the case has no test in this project.
 *
 * Writes matrix.md and matrix.json next to playwright.config.ts, and the
 * table to the GitHub job summary when there is one.
 */
type Cell = "pass" | "fail" | "missing" | "n/a";

const rank: Record<Cell, number> = { "n/a": 0, pass: 1, missing: 2, fail: 3 };

export default class MatrixReporter implements Reporter {
  private cells = new Map<string, Map<string, Cell>>();
  private projects = new Set<string>();

  onTestEnd(test: TestCase, result: TestResult): void {
    const id = /^(C\d+)\b/.exec(test.title)?.[1];
    const project = test.parent.project()?.name;
    if (!id || !project) {
      return;
    }
    this.projects.add(project);

    let cell: Cell;
    if (result.status === "skipped") {
      cell = "n/a";
    } else if (test.expectedStatus === "failed") {
      cell = result.status === "failed" || result.status === "timedOut" ? "missing" : "fail";
    } else {
      cell = result.status === "passed" ? "pass" : "fail";
    }

    const row = this.cells.get(id) ?? new Map<string, Cell>();
    const previous = row.get(project);
    if (!previous || rank[cell] > rank[previous]) {
      row.set(project, cell);
    }
    this.cells.set(id, row);
  }

  onEnd(): void {
    const order = ["http", "react", "vue", "svelte"];
    const projects = [...this.projects].sort((a, b) => order.indexOf(a) - order.indexOf(b));
    const rows = CASES.map(({ id, title }) => ({
      id,
      title,
      results: Object.fromEntries(projects.map((project) => [project, this.cells.get(id)?.get(project) ?? null])),
    }));

    const header = `| ID | Case | ${projects.join(" | ")} |\n| --- | --- | ${projects.map(() => "---").join(" | ")} |`;
    const lines = rows.map(
      ({ id, title, results }) => `| ${id} | ${title} | ${projects.map((project) => results[project] ?? "").join(" | ")} |`,
    );
    const counts = projects
      .map((project) => {
        const tally: Record<string, number> = {};
        for (const row of rows) {
          const cell = row.results[project];
          if (cell) tally[cell] = (tally[cell] ?? 0) + 1;
        }
        return `- **${project}**: ${Object.entries(tally)
          .map(([cell, count]) => `${count} ${cell}`)
          .join(", ")}`;
      })
      .join("\n");
    const untested = rows.filter(({ results }) => projects.every((project) => !results[project])).map(({ id }) => id);

    const markdown = [
      "# Conformance matrix",
      "",
      counts,
      untested.length ? `- **No test yet**: ${untested.join(", ")}` : "",
      "",
      header,
      ...lines,
      "",
    ].join("\n");

    const dir = resolve(import.meta.dirname, "..");
    writeFileSync(resolve(dir, "matrix.md"), markdown);
    writeFileSync(resolve(dir, "matrix.json"), JSON.stringify({ projects, cases: rows }, null, 2) + "\n");
    if (process.env.GITHUB_STEP_SUMMARY) {
      appendFileSync(process.env.GITHUB_STEP_SUMMARY, markdown);
    }
  }
}

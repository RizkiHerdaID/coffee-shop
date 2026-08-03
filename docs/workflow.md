# Parallel Agent Workflow — Coffee Shop

Visualization of the herdr fleet orchestration: Vikunja tracking, contract-first
dispatch, parallel agents, report verification, gates, and cleanup. The rules
behind this chart live in `AGENTS.md` (Workflow section) and
`.opencode/skills/herdr-parallel/SKILL.md` — when those change, update this chart.

```mermaid
flowchart TD
    Start([Task requested]) --> VikunjaCreate[Vikunja: create card<br/>POST /projects/6/tasks<br/>→ Pending, add label]
    VikunjaCreate --> Contract[Write /tmp/opencode/&lt;batch&gt;-contract.md<br/>spec · file ownership · conventions · do-not-touch]

    Contract --> Size{Fleet size<br/>by feature shape}
    Size -->|S effort| LeadOnly[Lead alone<br/>close spare panes ~700MB each]
    Size -->|M single-layer| LeadTester[Lead + tester]
    Size -->|M/L backend-only<br/>money/POS| LeadTestRev[Lead + tester + reviewer<br/>reviewer: contract-validation at dispatch<br/>+ phase-2 re-check of committed diff]
    Size -->|M/L multi-layer<br/>public UI + backend| LeadBack[Lead + backend + frontend + tester]

    LeadOnly --> Dispatch
    LeadTester --> Dispatch
    LeadTestRev --> Dispatch
    LeadBack --> Dispatch
    LeadOnly -.->|docs-only / research| MainCheckout[Main-checkout fleet<br/>no worktree, no Sail]

    Dispatch[Simultaneous dispatch<br/>ALL agents prompted in parallel<br/>same prompt template]

    MainCheckout -.-> Dispatch

    Dispatch --> SubAgents[Sub-agents: WRITE files only<br/>never git add/rm/commit<br/>report → /tmp/opencode/&lt;batch&gt;-&lt;role&gt;-report.md]
    Dispatch --> Lead[Lead: implement, run tests + Pint<br/>reads report files on disk<br/>NOT pane output]

    SubAgents --> LeadVerify[Lead verifies reports<br/>incorporates findings]
    Lead --> LeadVerify

    LeadVerify -->     DoneNotif[Only LEAD notifies<br/>herdr agent prompt w14:p1<br/>'DONE &lt;batch&gt;: ...']
    DoneNotif --> Ack[Main session ACKs<br/>so agent knows it was received]

    Ack --> GateCheck{Which gate?}

    GateCheck -->|Feature branch worktree| PreMerge[Pre-merge gate]
    PreMerge --> PM1[1. herdr agent list — all panes<br/>done/idle, unknown = hard block<br/>+ last action read]
    PM1 --> PM2[2. All &lt;batch&gt;-&lt;role&gt;-report.md exist<br/>findings incorporated]
    PM2 --> PM3[3. Review agent phase-2 re-check<br/>of committed diff, read-only]
    PM3 --> PM4[4. git status clean<br/>git log main..branch reviewed]
    PM4 -->     PM5[5. Merge → full suite on main<br/>→ teardown: sail down, workspace close,<br/>remove dir, git worktree prune]

    GateCheck -->|Docs-only main checkout| MainGate[Main-checkout gate]
    MainGate --> MG1[1. All reports exist +<br/>each agent confirmed DONE]
    MG1 --> MG2[2. Lead spot-checks claims<br/>against code — don't trust report]
    MG2 --> MG3[3. git status shows exactly<br/>expected staged files]
    MG3 --> MG4[4. Close fleet panes<br/>commit once on main]

    PM5 --> VikunjaDone[Vikunja: move card → Done<br/>PUT /buckets/19/tasks task_id=N<br/>verify done:true]
    MG4 --> VikunjaDone
    VikunjaDone --> DocsUpdate[Update matching feature doc<br/>+ roadmap.md completed section]
    DocsUpdate --> Finish([Done])

    SubAgents -.->|thin report / quiet pane| Corrective[Corrective prompt:<br/>re-read contract, redo,<br/>re-notify]
    Corrective --> SubAgents
```

## Legend

| Node | Meaning |
| --- | --- |
| Contract first | `contract.md` kills redundant research — every agent starts from the same context |
| Shape decides the fleet | S → lead alone; M single-layer → lead+tester; M/L backend-only (money/POS) → lead+tester+reviewer (frontend would idle — only Filament blades, backend-owned); M/L full-stack → lead+backend+frontend+tester |
| WRITE files only | Agents never touch git; concurrent `git add` on a shared index races and can corrupt it — the lead stages everything |
| Report files | `/tmp/opencode/<batch>-<role>-report.md` — fixed naming so "all reports exist" is a mechanical check; pane output scrolls out of reach |
| Only lead notifies | Prevents message storms when several agents finish at once |
| Two gates | Worktree fleets get the heavy pre-merge gate (idle panes, phase-2 review, suite); docs-only fleets get the lighter 4-step check |
| DONE is not evidence | The report file on disk + lead spot-check are the evidence, never the DONE message alone |
| Wait primitives | `pane wait-output --match` (shells/tests) and `agent prompt --wait` / `agent wait --until` (agents) replace sleep-polling — not turn-scoped, pair with the report-file check; always `--timeout` |

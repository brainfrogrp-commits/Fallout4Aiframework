# Project brief

The resumable record of your mod and its one current status. Your coding agent fills it in
from your conversation, asks only for what is missing and resumes at the first stage that is
not done and can be worked on ([docs/getting-started.md](docs/getting-started.md)). Keep it
short and factual. Never put keys, tokens, private URLs or personal data here: keys go on the
dashboard **API keys** page or in the private `config/config.php`. A blank row is not a
permission.

## Brief

| Question | Answer |
|---|---|
| Game, version and modding tools | Fallout 4 VR (Steam) with GingasVR Fallout VR Essentials Overhaul 20.1 (Wabbajack, MO2), F4SEVR 0.6.21, VR Address Library |
| Starting feature (new companion or existing NPC, and what it does) | Talk by voice to any existing NPC the player looks at or names nearby; later, up to 3-way conversations where NPCs also talk to each other |
| Mod name | Fallout 4 AI Framework |
| Project id and name in `dwemer-mod.json` (own values, set before deploy) | `fallout4-ai-framework`, Fallout 4 AI Framework |
| Voice (none, or PocketTTS replies; speech input only if asked: Parakeet push-to-talk into the editable draft) | Voice only: PocketTTS replies with no on-screen subtitles; speech input requested (Parakeet or Whisper push-to-talk on a VR controller button), since VR has no keyboard |
| LLM provider (name only) | OpenRouter |
| DwemerDistro (the one WSL distro name) | DwemerAI4Skyrim3 |

## Gameplay design

The agent fills this from the conversation before engine-port code, following
[Gameplay design](docs/GAMEPLAY_DESIGN.md); it is not a form for the user. One short answer
per row. A basic manual mod needs only a few words in each. JEV in "Decision and stakes"
today only picks which NPC answers a player's line (`decision.php`); other JEV choices are
planned.

| Design question | Answer |
|---|---|
| Player fantasy (one sentence) | Walk up to anyone in the Commonwealth in VR and just talk to them out loud, and have them talk back and to each other |
| Observable loop (what the player does, sees and hears in 2-3 minutes) | Look at or name an NPC, hold push-to-talk, speak, release; hear that NPC answer in their own voice; in a group, a second NPC may chime in |
| Interaction mode (manual, ambient/social, companion or operator; a mix is fine) | manual first; social (NPC-to-NPC inside a player-started conversation) second |
| Trigger and bounds (player line, or the one automatic event with its limits) | Player's spoken line only; NPC-to-NPC follow-ups capped at 2 lines per player line |
| Participants and listeners (who is present, who can hear) | The NPC looked at or named, plus up to 2 other live NPCs within talking distance; needs our own participant contract (not in KAGRENAC yet) |
| Decision and stakes (what can change; decided by a rule, the engine, JEV or dialogue) | Who answers: the addressed NPC, or JEV picks from participants; nothing else changes in the first slice |
| Actions and the outcome the player sees or hears | None at first; the outcome is heard voice |
| Memory (verified events only; one session or every session) | Conversation history per NPC across sessions |
| Missing mechanism and next bounded probe (or none) | Participant contract (server); VR gaze target and controller button read (plugin load probe first) |

## Integration plan (required before building mod code)

Follow [Engine planning](docs/ENGINE_PLANNING.md). Keep detailed sources and probes in
`ports/<engine>/PLAN.md`; summarize the chosen mechanisms below. Each row needs a cited
fact or an Unknown with its next bounded probe and chosen alternative. Server/provider
preparation can proceed while the game route is researched.

Default input is free typed text to a visible recipient in game, with controls captured
and released. Speech input is optional. Record the normal loader entry, offline/save
strategy and unavailable-server behavior. Use [installed startup acceptance](docs/ENGINE_PLANNING.md#installed-startup-acceptance)
for the handoff. Keep one current status below and generic friction in
`docs/KAGRENAC_FLOW_REVIEW.md`; do not restart completed stages when wording changes.

| Part | Plan | Fact (citation) or Unknown |
|---|---|---|
| Player input in game | | |
| NPC identity and lifetime | | |
| HTTP off the game thread, result back on it | | |
| Reply display | | |
| Audio playback | | |
| Game awareness: each value's source and freshness; unread values sent as `unavailable` | | |
| Action bridge (existing mechanism only): capability, target, instance check, preconditions | | |
| Saves, offline play, cancel | | |
| First thin slice and its acceptance | | |

## Stages and readiness

This table is the one current status of the mod; update a row whenever its state or evidence
changes. A stage is `done` only with evidence of the kind in its second column; otherwise it is
`not started`, `in progress`, `blocked: <what blocks it>; <next action>; <who>` or `not wanted`.
Stages 3 to 5 do not wait for stage 2: they run while engine unknowns are researched. Only
stage 6 needs the Gameplay design and the integration plan with an option chosen for every
Unknown. A blocked stage does not stop the others.

| Stage | Done only with | State | Latest evidence (date, kind) |
|---|---|---|---|
| 1. Brief: game, starting feature, mod name | source: the Brief rows above | done | 2026-10-28, source: brief from conversation |
| 2. Integration plan; each Unknown researched, with an option chosen | source: Gameplay design rows filled, with an existing mechanism for the requested loop and any automatic trigger, or the missing one named with its next probe; every plan row cited, or Unknown with an option chosen | in progress: install surveyed from f4sevr.log and modlist; loader and gaze probes next | 2026-10-28, source: user's f4sevr.log and modlist.txt, Mantella source |
| 3. DwemerDistro: own id committed, deployed | build: `project.ps1 deploy -WslDistro <name>` reported the commit deployed (health ok, exposure probes HTTP 403). Doctor output is diagnostic only; pairing is part of 6b | in progress: own id set; commit, then deploy | |
| 4. LLM | provider: the dashboard **Send test**, or a real `turn.php` turn (same server path), returned a real reply from the chosen provider (nothing has to answer on port 1234 unless that is the provider) | not started | |
| 5. Voice | user-reported or in-game: a PocketTTS reply heard on the dashboard **Speak** test or in the game (a generated WAV is not heard); or `not wanted` | not started | |
| 6a. Game-side build | build: the port's preflight passed and its build finished into the folders under Paths (for example its doctor, then its build exit 0 with a schema-2 `build.json`, [build records](docs/PORT_BUILD_RECORDS.md)) | not started | |
| 6b. Game-side runtime, paired | build: the runtime folder staged from that build (with its schema-2 `runtime.json`), `project.ps1 pair` wrote its private `config.json`, and the mod is installed where its qualified loader loads it on a normal game launch, with no per-session command, attach or helper start ([automatic load and connect](docs/ENGINE_PLANNING.md#automatic-load-and-connect)); a missing autoload mechanism keeps this blocked with its next probe; nothing launched yet | not started | |
| 6c. First prototype | in-game: the user started the game through its normal launch (its own entry, or a cited loader's documented offline entry) and the mod loaded and connected to this project's server by itself; the Gameplay design loop seen in the game, with its automatic trigger when one was requested and heard voice when voice was wanted; the player's own line typed in the in-game composer to the recipient it shows (and push-to-talk into that editable draft when speech input was asked for), with controls released afterwards; with only this project's route unreachable the game stayed playable and said so in game | not started | |
| 6d. Action `<name>` (one row per action) | in-game: that action's own check; a broad "it works" does not count | not started | |
| 7. Expand: next feature named | source | not started | |

## Paths

Everything built or private stays outside this repository and every web root. A port route's `-Root <folder>` (for
example on a disk with more room) replaces `%LOCALAPPDATA%\DwemerDynamics\Kagrenac\projects\fallout4-ai-framework`
in the game-side rows; record it here when you use one.

| What | Where |
|---|---|
| Client build (`project.ps1 setup` and `check` default) | `%LOCALAPPDATA%\DwemerDynamics\Kagrenac\projects\fallout4-ai-framework\client` |
| Game-side builds (the port's build route) | `%LOCALAPPDATA%\DwemerDynamics\Kagrenac\projects\fallout4-ai-framework\fo4vr\<part>` |
| Game runtime folder (staged by the port's route) with the private `config.json` (`pair -OutFile`) | `%LOCALAPPDATA%\DwemerDynamics\Kagrenac\projects\fallout4-ai-framework\runtime` |
| Content workspace ([content authoring](docs/CONTENT_AUTHORING.md): sources, review sheet, `import.json`) | `%LOCALAPPDATA%\DwemerDynamics\Kagrenac\projects\fallout4-ai-framework\content` |
| Deploy bundles and records (`project.ps1 deploy`) | `%LOCALAPPDATA%\DwemerDynamics\Kagrenac\deploy\fallout4-ai-framework` |
| Server copy and database in the DwemerDistro | `/var/www/html/Fallout4AIFrameworkServer` (`server_folder` in `dwemer-mod.json`), database `custom_fallout4_ai_framework` |

## Decisions

Record the repository layout here: one repo by default, or a user-chosen client/server
split with its build/deploy plan. Keep the current architecture and stage results aligned
with actual files. Report server-ready, port-built and gameplay-verified separately;
uncommitted code and interrupted checks remain explicit pending work.

| Date | Decision | Reason |
|---|---|---|
| 2026-10-28 | Started from KAGRENAC AI Mod Maker commit 1f845fbd0611 (equivalent of `project.ps1 new`, done by hand on Linux) | An independent copy with its own id; the template is unchanged |
| 2026-10-28 | One repository; game side in `ports/fo4vr/` | Template default |
| 2026-10-28 | Settings in an MCM VR menu; push-to-talk controller, button and hold/toggle configurable there | User request; MCM VR is already in the list and needs no terminal records |
| 2026-10-28 | Build the plugin with GitHub Actions (Windows runner) | User has no Visual Studio; the DLL is downloaded from the run |
| 2026-10-28 | Voice-only replies (no subtitles); push-to-talk speech input instead of the typed composer | User's choice; VR has no keyboard |

## Latest evidence

The last few results only; older detail goes in `ports/<engine>/`. Never record what did not
run. Kind is one of source, build, fixture, provider, user-reported or in-game; a broad
"it works" is not evidence for each action.

| Date | Kind | What ran (command or in-game step) | Result |
|---|---|---|---|
| 2026-10-28 | source | Copied template commit 1f845fbd0611 with `git archive`; set id, name and server folder | Nothing deployed or built yet |
| 2026-10-28 | source | Read user's f4sevr.log and MO2 modlist.txt | F4SEVR 0.6.21 loads 40 plugins; 246 full plugins, ~9 slots free; Mantella, F4SE_HTTP and TopicInfoPatcher present |

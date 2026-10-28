# fo4vr integration plan

Start with [Engine planning](../../docs/ENGINE_PLANNING.md). Facts below cite the user's
`f4sevr.log` and MO2 `modlist.txt` (shared 2026-10-28, not committed). Nothing is qualified in game yet.

## Target installation

| Item | Value | Evidence |
|---|---|---|
| Game | Fallout 4 VR, Steam, `C:\Library 2\steamapps\common\Fallout 4 VR` | f4sevr.log `plugin directory` |
| Modlist | GingasVR Fallout VR Essentials Overhaul 20.1 (Wabbajack, MO2) | user-reported |
| Loader | F4SEVR 0.6.21, runtime id `010A08A0` | f4sevr.log line 1 |
| Address library | VR Address Library for F4SEVR (enabled) | modlist.txt |
| Saves | `Documents\My Games\Fallout4VR\__MO_Saves` (MO2 profile-local saves) | f4sevr.log |
| Load order | 246 full plugins loaded (index 0-245, `Mantella.esp` at 244); light-plugin list empty | f4sevr.log `Saving mod list` |
| Free plugin slots | About 9 before the 255-plugin limit. This mod should add **at most one** ESP, ideally none | derived from the above |
| Plugin load problems already present | `diskCacheEnabler.dll` fails (Error 126, missing dependency); `CellOffsetGeneratorF4SE.dll` reports incompatible. Not ours; leave as is | f4sevr.log |

### Relevant mods already loaded

| Mod / DLL | Why it matters |
|---|---|
| Mantella (`Mantella.esp`, `F4MantellaLauncher.dll`) + "Mantlla Root Fix" | Existing AI-NPC route on this list. Study it; don't run both at once in testing |
| `F4SE_HTTP.dll` | HTTP from Papyrus (used by Mantella). Option for a script-side route |
| `TopicInfoPatcher.dll` | Lets a dialogue topic play replaced voice files; how Mantella gets lip-synced in-world NPC voice |
| Garden of Eden Papyrus Extender VR, Papyrus Common Library VR, FO4VRTools, MCM VR | Script helpers and a settings menu we can use |
| `CompanionVoiceCommands.dll` | Already listens to the microphone for companion commands. Check push-to-talk does not clash |
| Mute Player Voice, Realistic Conversations, Enhanced Dialogue Interface | Vanilla dialogue changes; our voice must not collide with real dialogue scenes |
| FRIK, VRHUDControl, F4VRControlsConfig | VR body / HUD / controller bindings; pick a free controller button for push-to-talk |

## How Mantella's current Fallout 4 build speaks (source: Mantella `src/games/fallout4.py`, commit c0c1ae6)

The server writes each TTS line as a `.fuz` (audio + lip data) into the mod's
`Sound\Voice\Mantella.esp\<voice type>\` folder over a placeholder dialogue line, then the game
plays that topic on the NPC: positional, through the game's audio, with lip sync. A new voice-type
folder needs a game restart. This is the reference for voice route B below.

## How Mantella's game side works in VR (source: user's Mantella `Scripts\Source\User\*.psc`, shared 2026-10-28, not committed)

- **Voice:** `NpcSpeak()` in `MantellaConversation.psc` calls `actor.SetOverrideVoiceType(MantellaVoice)` (one shared voice type),
  `TopicInfoPatcher.PatchTopicInfo(topic, line)`, then `actor.Say(topic)` and clears the override. The `.fuz` for the
  line sits in that one voice type's folder, so no per-NPC voice folders are needed for this route.
- **Recipient in VR:** crosshair events are disabled when `isFO4VR`; the player starts a conversation with the
  "Mantella gun" (shoot the NPC) or an activation perk. No gaze targeting.
- **Transport:** Papyrus `F4SE_HTTP.sendLocalhostHttpRequest`; in VR it polls on a timer (`HttpPolling`) instead of events.
- **Microphone:** handled by the Mantella desktop app, not the game; VR forces mic on.
- **Settings:** a holotape (`MantellaSettingsHolotape`) with terminal pages, played from the Pip-Boy; needs ESP records.
- **Group talk:** a `Participants` list; the line is spoken to the last speaker or the other participant.

## Settings menu and push-to-talk button (user requests 2026-10-28)

- **Menu: MCM VR** (already in the list, `mcm_vr.dll`). Ship `MCM\Config\Fallout4AIFramework\config.json`;
  values are saved to `MCM\Settings\Fallout4AIFramework.ini`, which the plugin re-reads when the MCM menu closes.
  No terminal records needed, unlike a Mantella-style holotape. Unknown: whether MCM VR lists a mod that has no ESP;
  probe with the first build (if not, the menu waits for the one ESP that voice route B needs anyway).
- **Push-to-talk:** configurable in MCM: which controller (left/right), which button (thumbstick click, A/B/X/Y,
  grip, trigger), and hold vs. toggle. The plugin polls the OpenVR controller state each frame, as other FO4VR plugins
  do. Polling does not block the game's own use of that button, so the default should be one the list leaves free.
- Holotape/Pip-Boy menu stays a later option if MCM VR proves awkward in the headset.

## Candidate architecture

| Part | Candidate | Unknown and next probe |
|---|---|---|
| Loader and lifecycle | F4SEVR C++ plugin (x64) on a VR-compatible CommonLibF4 fork; MO2 launch loads it like the 40 others. Built by GitHub Actions on Windows, so the user needs no compiler | Which fork builds for runtime `010A08A0`. Probe: a DLL that only logs its load |
| Recipient | NPC in the player's view (crosshair/gaze) or named nearby ("Piper, ..."); identity = ref handle + form id | VR crosshair-target read. Probe: log target name on button press |
| Participants (3-way talk) | Up to 3 nearby live NPCs within talking distance, chosen by the game | **Not in KAGRENAC's protocol yet** (`turn.php` is one player line to one NPC; GAMEPLAY_DESIGN calls this "the planned participant contract"). We extend our own server: participant list, who answers next (JEV `decision.php` already picks an answerer from candidates), bounded NPC-to-NPC lines |
| Input | Push-to-talk on a configurable controller button (MCM); mic captured by the plugin; Parakeet (default) or faster-whisper STT in the distro | Controller-button read; mic clash with CompanionVoiceCommands |
| Display | None (user choice). One short notification only when the server is unreachable | VR notification route |
| Settings | MCM VR menu (see above) | Does MCM VR list a mod with no ESP |
| Transport | Shared `client/` `TurnRuntime` on a worker thread; results applied on the game's main thread | Main-thread task API in the fork |
| Voice | **A (first slice):** plugin plays the PocketTTS WAV, non-positional. **B (target):** Mantella-style `.fuz` over placeholder topics via TopicInfoPatcher, needing our one ESP | B needs one plugin slot and per-voice-type folders |
| Awareness | Location, NPC name/race/voice type, player name, time; anything unread sent as `unavailable` | Per-field API |
| Actions | None in the first slice | — |
| Saves, offline, cancel | Cancel on save load, cell change, recipient unload; game stays playable if the server is down | — |

## First thin slice and acceptance

Launch the list through MO2 normally with Mantella disabled; hold the push-to-talk button while
looking at a companion, speak, release; hear that NPC answer (route A). With the server stopped,
the game stays playable and shows one short notification. 3-way talk and route B come after.

# fo4vr integration plan

No reference plan ships for this engine. Start with [Engine planning](../../docs/ENGINE_PLANNING.md).
Nothing below is qualified yet: each row is a candidate route with the evidence still needed.

## Target installation (to be surveyed on the user's PC)

| Item | Value | Evidence |
|---|---|---|
| Game | Fallout 4 VR, Steam | user-reported |
| Game build and `Fallout4VR.exe` SHA-256 | Unknown | survey |
| Modlist | GingasVR "Fallout VR Essentials Overhaul" via Wabbajack; version Unknown | Wabbajack / MO2 profile |
| Loader | F4SEVR via MO2 (`f4sevr_loader.exe`); version Unknown | MO2 executables list, `f4sevr.log` |
| Address library | VR Address Library for F4SEVR; version Unknown | MO2 mod list |
| Existing AI mods | Mantella (FO4VR) if installed; to dissect, not to ship | user to share |
| Plugin count / free load-order slots | Unknown | `plugins.txt` |

## Candidate architecture

| Part | Candidate | Unknown and next probe |
|---|---|---|
| Loader and lifecycle | F4SEVR C++ plugin (x64 DLL) built on a VR-compatible CommonLibF4 fork; loads on normal MO2 launch | Which CommonLibF4 fork builds against the user's F4SEVR; probe: a plugin that only logs its load |
| NPC and player | Crosshair/look target or current companion; `RefHandle` + form id as identity | How to read the VR crosshair target and current companion; probe: log target name on button press |
| Input | Push-to-talk on a controller button, mic captured by the plugin, Parakeet or Whisper STT on the server | Reading a VR controller button from F4SEVR (OpenVR hook vs. game input events) |
| Display | None: no subtitles (user choice); a short in-game notification only for errors / server unavailable | Notification route in VR HUD |
| Transport | Shared `client/` library (`TurnRuntime`) on a worker thread; results applied via the game's task queue | Main-thread task interface in the chosen CommonLibF4 fork |
| Voice playback | First slice: plugin plays the PocketTTS WAV (non-positional). Later: positional / lip-synced via the game's dialogue system (study Mantella's approach) | Whether in-process WAV playback works alongside the VR audio device |
| Awareness | Location, NPC name/race, player name, time; anything unread sent as `unavailable` | Per-field API in CommonLibF4 |
| Actions | None in the first slice | — |
| Saves, offline, cancel | Cancel on save load, cell change, target unload; game stays playable if the server is down | — |

## First thin slice and acceptance

Launch the GingasVR list through MO2 normally; hold the push-to-talk button while looking at a
companion, speak a line, release; hear that NPC answer in a PocketTTS voice. With the server
stopped, the game stays playable and shows one short notification.

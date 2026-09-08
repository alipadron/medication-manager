# Medication Manager — Product Spec

Status: shaped (grilling complete). Not yet built.
Owned by: alipadron (Alex) + wife. Two-person household.

## Purpose
A PWA for managing household medications: reliable schedules that survive real
life, and awareness of what medicine you have and when you'll run out.

## Core problem being solved
Standard phone alarms fail in practice: you don't hear them, you're at the
laptop and away from the phone, or you leave without the meds. When you miss,
you must manually re-shift every following alarm. Worse when a whole family is
sick. Separately, you forget to re-buy a package before a blister empties, and
you have a box of leftover meds from older treatments with no record of what's
in it or whether it's expired.

## Users
- Two people: Alex and his wife.
- One SHARED household schedule (a "person" profile for each). No full
  multi-account auth in v1.
- Kids may be added as profiles later (out of scope for v1).

## Delivery
- PWA, one codebase running on the household's Android, iPhone, Windows, and
  Linux devices.
- Web push for notifications (which are delivered to EVERY enrolled device at
  once — Alex's phone AND laptop).

## v1 = the schedule that re-shifts itself
1. Late dose -> shift that day's remaining doses by the delay, but NEVER push
   two doses closer together than a safe minimum interval.
2. Skipped dose -> flag it, leave the rest of the day's schedule fixed.
3. No manual re-entering of alarms after a miss.
4. Every reminder is delivered to all enrolled devices simultaneously, plus a
   manual "nudge the other person" button.
5. No loud-tone escalation in v1.

## Slice two = stock
- (a) Refill forecast: "this blister has 4 doses left, refill by Tuesday."
- (b) Home inventory of leftover meds, including expiry dates.
Both share the same data model: medication -> package (supply) -> schedule.

## Storage (REQUIRED, non-negotiable)
- OFFLINE-FIRST client: the app works fully on-device with no power/network.
- Managed CLOUD sync backend, reachable over phone data.
- EXPLICITLY NOT self-hosted — Venezuela has daily power outages; a local
  server would be down with the power.

## Out of scope (v1)
- Kids as managed users / true multi-account auth
- Native iOS/Android apps (evaluate only if PWA notifications prove too weak)
- Pharmacy / prescription integrations
- Loud-tone escalation
- Going to market. Purpose is a personal tool first; "sell it for financial
  independence" is decided later by proof-of-use, not pre-built now.

## Open for the build phase
- Exact frontend stack (planned: React + Vite + PWA plugin, offline via
  IndexedDB/Dexie)
- Sync backend stack and deployment target
- Medication entry UX and safe-dose-interval defaults
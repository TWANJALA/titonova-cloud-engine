# TitoNova Inline Editing UX Intelligence Rules

## 0. Core Philosophy (Non‑Negotiable)
Inline editing must feel invisible.
Users should never think about “editing.”
They should think: “I’m just fixing my website.”

TitoNova Inline Editing is not a feature.
It is the default state of ownership.

Users are not “editing websites”.
They are shaping outcomes — instantly.

---

## 1. Activation & Intent Recognition
- **Predictive edit intent:** On hover or focus, detect common editable elements (headlines, paragraphs, buttons, prices, phone, hours, CTA blocks).
- **Soft affordance only:** Show a subtle highlight or underline *after* a 300–500ms dwell, never instantly.
- **No mode switches:** Do not enter a “global edit mode.” Editing should be local, lightweight, and reversible.
- **Respect user flow:** Never interrupt scrolling or interaction. Let users click normally unless they intentionally pause on an editable element.

### Hover Auto‑Activation Rule (UI Micro‑Behavior)
**Rule:** Editable elements auto‑activate on hover.

**When cursor enters an editable zone:**
- Show a subtle outline (1px glow, brand color, 40% opacity).
- Show a micro‑label: “Click to edit” (fade‑in 120ms).
- Cursor changes to text‑edit or pointer based on content type.

**When cursor exits:**
- Remove all UI indicators immediately.

## 2. Entry Behavior (Frictionless)
- **Single action to edit:** Click or tap directly to edit; no modal, no side panel.
- **Immediate caret placement:** Cursor appears exactly where the user clicked.
- **Smart text selection:** If the user clicks on an isolated CTA or label, auto-select the whole text.
- **Keyboard continuity:** If the user starts typing, replace selected text seamlessly.

## 3. Editing Experience (Invisible UI)
- **No heavy chrome:** Avoid toolbars unless formatting is essential. If needed, use a compact floating control that appears only on selection.
- **Live preview:** Changes show immediately on the page.
- **Natural text flow:** Respect line breaks and layout; editing should not cause layout jumps.
- **Consistent typography:** Use the site’s actual fonts, sizes, and colors while editing.

## 4. Save Intelligence (Automatic + Reliable)
- **Auto-save by default:** Save on blur, pause (>= 800ms), or explicit Enter (for single-line fields).
- **Zero “Save” pressure:** Do not require users to hunt for a save button. If a save control exists, keep it quiet and optional.
- **Optimistic UI:** Show success immediately; reconcile in background.
- **Failure recovery:** On error, show a subtle inline warning with a one-click retry, and never lose the user’s text.

## 5. Undo & Confidence
- **Always reversible:** Provide a visible but minimal “Undo” toast after each save (3–6s).
- **Multi-step undo:** Keep a short session history (at least 10 changes).
- **Clear diffing:** If possible, show what changed in the undo label (e.g., “Undo: Headline”).

## 6. Field-Specific Intelligence
- **Phone numbers:** Auto-format to local standard, maintain tap-to-call.
- **Emails:** Validate format; keep mailto.
- **Prices:** Preserve currency and separators.
- **CTAs:** Ensure button padding and styles remain intact.
- **Hours & addresses:** Normalize formatting while preserving meaning.

## 7. Mobile & Touch
- **Touch-first:** Tap once to edit; long-press should open selection or context menu, not a different edit mode.
- **Avoid hover logic:** Use tap-based intent detection on touch devices.
- **Prevent accidental edits:** If the user is scrolling, suppress edit activation.

## 8. Accessibility & Inclusivity
- **Keyboard access:** Allow `Tab` navigation into editable regions.
- **ARIA hints:** Use `aria-label` or `aria-describedby` to clarify editable intent.
- **Screen reader clarity:** Announce “Editable text” on focus.
- **Respect reduced motion:** No flashing or animated distractions.

## 9. Trust & Transparency
- **No silent destructive changes:** Confirm or highlight when edits alter structure (e.g., removing a section).
- **No hidden lock-in:** If a block is not editable, show a calm explanation.
- **Change ownership:** If multi-user, show who edited last (subtle, optional).

## 10. Performance & Stability
- **Instant activation:** Hover highlights should be smooth and low-latency.
- **No layout shift:** Editing should not reflow the page unless user text requires it.
- **Offline safety:** Queue edits if connection drops; retry automatically.

---

## Microcopy Standards (Minimal + Human)
- “Saved” (not “Your changes have been saved”).
- “Undo: Button text”
- “Couldn’t save. Retry”
- “This section can’t be edited here.”

---

## Success Criteria
- User completes an edit without noticing a tool.
- User never needs to look for a save button.
- User trusts that edits are safe, reversible, and instant.

---

## Guardrails (Do Not Break)
- Do not force global edit modes.
- Do not open popups for simple text edits.
- Do not interrupt scrolling or selection.
- Do not lose user input under any error condition.

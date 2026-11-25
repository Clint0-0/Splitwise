# Bill scanning: provider evaluation (Phase 6)

Desk evaluation, 2026-11-24. **No provider has been tested with real bills yet** (no API keys in the build environment). Prices are list prices from the pages linked below; confirm them before choosing.

> A Claude chat subscription (Pro/Max, "Claude Opus" in the app) does **not** give the application API access. Any provider below needs its own API account, key and billing. The key goes into the host's secret settings as `SCAN_API_KEY`, never into git, chat or the Docker image.

## What we need from a reader
Indian restaurant/shop bills: thermal paper, faded print, GST lines (CGST/SGST), service charge, discounts, round-off, odd layouts, phone photos in poor light. Output must be structured: merchant, date, lines (name, qty, line total), charges, grand total, and a way to flag doubtful lines. The app then lets the person correct everything and assign people; **nothing is saved without their review tick**.

## Options

| | Vision LLM (Claude Sonnet 5.5) | Vision LLM (Claude Haiku 5.5) | AWS Textract AnalyzeExpense | Google Document AI Expense parser | Azure Document Intelligence (receipt) |
|---|---|---|---|---|---|
| List price | $2 / M input tokens, $10 / M output | $0.10 / M input, $0.50 / M output (prompts ≤ 100k tokens) | ~$0.01 per page (first 1M pages) | $0.10 per document (up to 10 pages) | Not shown on the public page (needs the calculator) |
| Rough cost per bill | ≈ $0.008 (≈ ₹0.7): ~1.6k image + ~0.3k prompt tokens in, ~0.4k out | ≈ $0.0004 | ≈ $0.01 | ≈ $0.10 | ? |
| Output shape | Our own JSON schema (merchant, date, items, charges, total, `uncertain` per line) in one call | same | Fixed expense fields + line items; we map them | Fixed entity types; we map them | Fixed receipt fields; we map them |
| Messy / thermal bills | Good at reading context (e.g. tells GST lines from items), can say a line is unsure; may also *invent* plausible text → must be reviewed | Cheaper, likely weaker on hard photos: measure | Trained receipt model; less flexible with unusual layouts | Trained receipt model | Trained receipt model |
| Integration work | Done (`SCAN_PROVIDER=anthropic`) | Same code: `SCAN_MODEL=claude-haiku-5-5` | New provider class + AWS SigV4 signing | New provider class + Google auth | New provider class |
| Data leaves to | Anthropic | Anthropic | AWS | Google | Microsoft |

Image token count is an estimate (Anthropic's vision guide gives width × height / 750 for images up to ~1.15 megapixels; verify in the current docs). Phone photos are larger than that and get scaled down by the API.

## Recommendation
Keep the implemented **Claude vision provider** as the first candidate: lowest integration effort (already built and tested against a fake API), cheap per scan, and it returns exactly our structure including charges and doubtful lines. Then **measure** before switching it on:

1. Collect 30–50 real bills (with permission, no personal data you don't need): thermal, crumpled, dim light, handwritten totals, long bills, GST and non-GST, discounts, round-off.
2. Run each through Sonnet 5.5 and Haiku 5.5 (`SCAN_MODEL`), record per bill: lines correct, amounts correct, charges correct, total correct, time, cost (from the API's `usage`).
3. Pass mark suggestion: total correct on ≥ 95 % of bills, every wrong line either flagged `uncertain` or caught by the "lines ≠ total" warning.
4. Only if both fail, try Textract AnalyzeExpense (needs a new provider class).

## Safety rules already in the code
- Feature flag `SCAN_PROVIDER` (off by default); `mock` refused in production.
- Per-user daily limit (`SCAN_DAILY_LIMIT`) and site-wide monthly cap (`SCAN_MONTHLY_LIMIT`); admin page shows scans, failures and estimated cost (`SCAN_COST_PAISE`). Real cost: the provider's billing page.
- Never decides who ate what; never saves; requires the "I checked it against the bill" tick; flags doubtful lines and sum/total mismatches; manual entry always available.
- The photo is sent to the provider, not stored by SplitWise+; the form says so.

## Sources
- [Claude API pricing](https://platform.claude.com/docs/en/about-claude/pricing)
- [Amazon Textract pricing](https://aws.amazon.com/textract/pricing/)
- [Google Document AI pricing](https://cloud.google.com/document-ai/pricing)
- [Azure Document Intelligence pricing](https://azure.microsoft.com/pricing/details/ai-document-intelligence/)


## Update 2026-11-25: Groq added

`SCAN_PROVIDER=groq` is implemented (owner's choice). It uses Groq's OpenAI-compatible chat completions endpoint with a vision model (default `qwen/qwen3.8-27b`, changeable with `SCAN_MODEL` because Groq renames models) and JSON mode. Each image counts as about 2,048 input tokens on that model; check Groq's pricing page for the current per-token price and set `SCAN_COST_PAISE` to match. The same measurement plan above applies: run 30–50 real bills and record line accuracy before relying on it.

# JShrink Architecture

## Purpose

A pure-PHP JavaScript minifier that removes whitespace, comments, and other
redundant characters from JavaScript source without altering its semantics.

## Directory Structure

```
src/
  J_Shrink/
    Minifier.php   — the single public class
tests/
  J_Shrink/Test/
    J_Shrink_Test.php  — PHPUnit test suite
```

## Key Design Decisions

- **Static entry point**: `Minifier::minify(string $js, array $options = [])` is
  the sole public API; it constructs a private instance and discards it after use.
- **Output-buffering**: the minification loop writes directly to PHP's output
  buffer via `echo`, which is then captured with `ob_get_clean()`.  This avoids
  building large intermediate strings.
- **Lock/unlock pattern**: sequences like `x++ + ++y` are temporarily replaced
  with a unique lock token before minification and restored afterwards to prevent
  accidental `+++` merging.
- **Flagged comments**: `/*! … */` and `/*@ … */` blocks are preserved by default
  (configurable via `'flaggedComments' => false`) for licence headers.
- **Regex heuristics**: the parser detects regex literals using contextual
  lookahead (preceding operator characters) to distinguish `/` division from
  `/regex/` patterns.

## Extension Points

- Pass `['flaggedComments' => false]` to strip all block comments including
  licence headers.
- Subclass `Minifier` and override `process_one_line_comments()` or
  `process_multi_line_comments()` for custom comment handling.

## Dependency Flow

```
Consumer → Minifier::minify()   (static)
               └── Minifier (instance — created and destroyed per call)
```

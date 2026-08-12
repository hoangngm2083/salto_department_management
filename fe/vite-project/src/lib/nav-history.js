const STACK_KEY = 'nav_history_stack';
const MAX_DEPTH = 20;

function readStack() {
  try {
    return JSON.parse(sessionStorage.getItem(STACK_KEY)) ?? [];
  } catch {
    return [];
  }
}

function writeStack(stack) {
  sessionStorage.setItem(STACK_KEY, JSON.stringify(stack));
}

/**
 * Records the path being left behind by a forward navigation, so a later
 * popNavHistory() call can send a "back" link there instead of guessing a
 * target from whatever data happens to be loaded on the destination page
 * (which may reference a since-deleted record, or simply not be where the
 * user actually came from).
 */
export function pushNavHistory(path) {
  const stack = readStack();
  stack.push(path);

  if (stack.length > MAX_DEPTH) {
    stack.shift();
  }

  writeStack(stack);
}

/**
 * Removes and returns the most recently recorded path, or null if there's
 * nothing to go back to (e.g. the page was opened directly or via reload
 * with an empty stack).
 */
export function popNavHistory() {
  const stack = readStack();
  const path = stack.pop() ?? null;
  writeStack(stack);

  return path;
}

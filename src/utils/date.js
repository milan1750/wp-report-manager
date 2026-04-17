// utils/date.js
export const normalizeWeekStart = (apiDay) => {
  if (apiDay === null || apiDay === undefined) return 1; // Monday default
  return ((apiDay % 7) + 7) % 7; // safe normalize
};

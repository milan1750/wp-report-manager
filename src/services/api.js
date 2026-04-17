const api = window.WRM_API;

export const fetchPermissions = async () => {
  const res = await fetch(`${api.url}permissions`, {
    headers: { "X-WP-Nonce": api.nonce },
  });
  return res.json();
};

export const fetchWeeks = async () => {
  const res = await fetch(`${api.url}weeks`, {
    headers: { "X-WP-Nonce": api.nonce },
  });
  return res.json();
};

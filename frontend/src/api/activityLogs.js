import httpClient from './httpClient';

export const getActivityLogs = (params) =>
  httpClient.get('/activity-logs', { params });

export default {
  getActivityLogs,
};

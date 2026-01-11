import httpClient from './httpClient';

export const getManualBalances = (params) => httpClient.get('/manual-balances', { params });
export const createManualBalance = (data) => httpClient.post('/manual-balances', data);

export default {
  getManualBalances,
  createManualBalance,
};

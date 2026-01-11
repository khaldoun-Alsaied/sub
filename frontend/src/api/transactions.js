import httpClient from './httpClient';

export const getTransactions = (params) => httpClient.get('/transactions', { params });

export const createTransaction = (data) => httpClient.post('/transactions', data);

export const updateTransaction = (id, data) => httpClient.patch(`/transactions/${id}`, data);

export const deleteTransaction = (id) => httpClient.delete(`/transactions/${id}`);

export default {
  getTransactions,
  createTransaction,
  updateTransaction,
  deleteTransaction,
};

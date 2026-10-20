// Invented claims only. No receipt in this module establishes native evidence.
import { dataObject } from './native-witness-profile.mjs';
export const lossCase = 'selected_commit_response_loss';
const summaryKeys = ['case', 'checks', 'groups', 'returned_false', 'pre_guards', 'post_guards', 'root_erased'];
export function lossSummary(value) {
	return dataObject(value, summaryKeys) && value.case === lossCase && Number.isInteger(value.checks) && value.checks >= 1 && value.checks <= 8192 &&
		Number.isInteger(value.groups) && value.groups >= 1 && value.groups <= 32 && value.returned_false === true && value.pre_guards === 8 && value.post_guards === 0 && value.root_erased === true;
}
const receiptKeys = ['worker_exit_zero', 'pipes_closed', 'stderr_empty', 'listener_closed', 'sessions_absent', 'locks_available',
	'fixtures_absent', 'application_unchanged', 'ledger_matches', 'other_rows_unchanged', 'guards_passed'];
export const lossReceipt = (value) => dataObject(value, receiptKeys) && receiptKeys.every((key) => value[key] === true);
export const lossLimits = Object.freeze({ window: 10000000000n, remaining: 6000000000n, cleanup: 28000000000n });

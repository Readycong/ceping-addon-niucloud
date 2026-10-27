/**
 * 答题草稿：key = ceping_draft_<testId>_<mode>，默认 24h
 */
export interface DraftData {
	answers: AnyObject[]
	ts: number
}

export function draftKey(id: number, mode: string): string {
	return `ceping_draft_${id}_${mode}`
}

export function saveDraft(id: number, mode: string, answers: AnyObject[], expireH = 24): void {
	const data: DraftData = { answers, ts: Date.now() }
	uni.setStorageSync(draftKey(id, mode), JSON.stringify(data))
}

export function loadDraft(id: number, mode: string, expireH = 24): AnyObject[] | null {
	try {
		const raw = uni.getStorageSync(draftKey(id, mode))
		if (!raw) return null
		const data: DraftData = typeof raw === 'string' ? JSON.parse(raw) : raw
		if (Date.now() - data.ts > expireH * 3600 * 1000) {
			uni.removeStorageSync(draftKey(id, mode))
			return null
		}
		return data.answers || []
	} catch (e) {
		return null
	}
}

export function clearDraft(id: number, mode: string): void {
	uni.removeStorageSync(draftKey(id, mode))
}

import request from '@/utils/request'

/**
 * 测评记录列表
 */
export function getRecordList(params: Record<string, any>) {
    return request.get('ceping/record/lists', params)
}

/**
 * 测评记录详情
 */
export function getRecordInfo(id: number) {
    return request.get('ceping/record/info', { id })
}

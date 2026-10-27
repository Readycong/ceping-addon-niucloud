import request from '@/utils/request'

/**
 * 题库列表
 */
export function getTestList(params: Record<string, any>) {
    return request.get('ceping/test/lists', params)
}

/**
 * 题库详情（只读全量题目/结果）
 */
export function getTestInfo(id: number) {
    return request.get('ceping/test/info', { id })
}

/**
 * 上下线
 */
export function setTestOnline(id: number, online_status: number) {
    return request.put('ceping/test/online', { id, online_status })
}

/**
 * 热门标记
 */
export function setTestHot(id: number, is_hot: number) {
    return request.put('ceping/test/hot', { id, is_hot })
}

/**
 * 排序设置
 */
export function setTestSort(id: number, sort: number) {
    return request.put('ceping/test/sort', { id, sort })
}

import request from '@/utils/request'

/***************************************************** 示例条目 ****************************************************/

/**
 * 列表
 */
export function getItemList(params: Record<string, any>) {
    return request.get(`{{KEY}}/item`, params)
}

/**
 * 详情
 */
export function getItemInfo(id: number) {
    return request.get(`{{KEY}}/item/${id}`)
}

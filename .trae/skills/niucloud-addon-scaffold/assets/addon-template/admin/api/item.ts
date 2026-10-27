import request from '@/utils/request'

/***************************************************** 示例条目 ****************************************************/

/**
 * 列表
 */
export function getItemList(params: Record<string, any>) {
    return request.get(`{{KEY}}/item`, { params })
}

/**
 * 详情
 */
export function getItemInfo(id: number) {
    return request.get(`{{KEY}}/item/${id}`)
}

/**
 * 添加
 */
export function addItem(params: Record<string, any>) {
    return request.post('{{KEY}}/item', params, { showSuccessMessage: true })
}

/**
 * 编辑
 */
export function editItem(params: Record<string, any>) {
    return request.put(`{{KEY}}/item/${params.id}`, params, { showSuccessMessage: true })
}

/**
 * 删除
 */
export function deleteItem(id: number) {
    return request.delete(`{{KEY}}/item/${id}`, { showSuccessMessage: true })
}

import request from '@/utils/request'

/***************************************************** {{COMMENT}} ****************************************************/

/**
 * 列表
 */
export function get{{MODULE_PASCAL}}List(params: Record<string, any>) {
    return request.get(`{{KEY}}/{{MODULE}}`, { params })
}

/**
 * 详情
 */
export function get{{MODULE_PASCAL}}Info(id: number) {
    return request.get(`{{KEY}}/{{MODULE}}/${id}`)
}

/**
 * 添加
 */
export function add{{MODULE_PASCAL}}(params: Record<string, any>) {
    return request.post(`{{KEY}}/{{MODULE}}`, params, { showSuccessMessage: true })
}

/**
 * 编辑
 */
export function edit{{MODULE_PASCAL}}(params: Record<string, any>) {
    return request.put(`{{KEY}}/{{MODULE}}/${params.id}`, params, { showSuccessMessage: true })
}

/**
 * 删除
 */
export function delete{{MODULE_PASCAL}}(id: number) {
    return request.delete(`{{KEY}}/{{MODULE}}/${id}`, { showSuccessMessage: true })
}

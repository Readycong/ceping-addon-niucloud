import request from '@/utils/request'

/***************************************************** {{COMMENT}} ****************************************************/

/**
 * 列表
 */
export function get{{MODULE_PASCAL}}List(params: Record<string, any>) {
    return request.get(`{{KEY}}/{{MODULE}}`, params)
}

/**
 * 详情
 */
export function get{{MODULE_PASCAL}}Info(id: number) {
    return request.get(`{{KEY}}/{{MODULE}}/${id}`)
}

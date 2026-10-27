import request from '@/utils/request'

/**
 * 获取 AI 与广告配置
 */
export function getCepingConfig() {
    return request.get('ceping/config')
}

/**
 * 保存 AI 与广告配置
 */
export function setCepingConfig(params: Record<string, any>) {
    return request.post('ceping/config', params)
}

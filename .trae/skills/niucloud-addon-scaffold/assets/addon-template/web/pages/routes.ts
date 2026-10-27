export default [
    {
        path: '/{{KEY}}/item/list',
        component: () => import('~/addon/{{KEY}}/pages/item/list.vue')
    }
]

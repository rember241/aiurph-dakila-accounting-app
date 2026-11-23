import { z } from 'zod'

const UUID_ANY_VERSION = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i

export const zUuid = () => z.string().regex(UUID_ANY_VERSION, 'Invalid UUID')

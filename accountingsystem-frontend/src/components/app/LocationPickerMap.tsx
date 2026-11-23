'use client'

/**
 * LocationPickerMap — pure Leaflet map, SSR-excluded.
 *
 * Import via `LocationPicker.tsx` which wraps this with a dynamic import
 * so the module never reaches the Next.js server renderer.
 */

import { useEffect } from 'react'

import L from 'leaflet'
import { MapContainer, Marker, TileLayer, useMap, useMapEvents } from 'react-leaflet'

import 'leaflet/dist/leaflet.css'

// ─── Fix broken default marker icons bundled by webpack ───────────────────────

const defaultIcon = L.icon({
  iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
  iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
  iconSize: [25, 41],
  iconAnchor: [12, 41],
  popupAnchor: [1, -34],
  shadowSize: [41, 41]
})

L.Marker.prototype.options.icon = defaultIcon

// ─── Default map centre: Metro Manila, Philippines ────────────────────────────

const DEFAULT_CENTER: [number, number] = [14.5995, 120.9842]
const DEFAULT_ZOOM = 12

// ─── Sub-components ───────────────────────────────────────────────────────────

/**
 * Programmatically flies the map to a new coordinate whenever `center` changes.
 */
function FlyToController({ center }: { center: [number, number] | null }) {
  const map = useMap()

  useEffect(() => {
    if (center) {
      map.flyTo(center, Math.max(map.getZoom(), 15), { duration: 0.8 })
    }
  }, [center, map])

  return null
}

/**
 * Fires `onPick` whenever the user clicks an empty area of the map.
 */
function ClickHandler({ onPick }: { onPick: (lat: number, lng: number) => void }) {
  useMapEvents({
    click(e) {
      onPick(e.latlng.lat, e.latlng.lng)
    }
  })

  return null
}

// ─── Props ────────────────────────────────────────────────────────────────────

export type LocationPickerMapProps = {

  /** Current pin position (null = no pin). */
  position: [number, number] | null

  /** Called when the user clicks the map or drags the marker. */
  onPick: (lat: number, lng: number) => void

  /** Height of the map container (CSS string). */
  height?: string | number
}

// ─── Main export ──────────────────────────────────────────────────────────────

export default function LocationPickerMap({ position, onPick, height = 260 }: LocationPickerMapProps) {
  return (
    <MapContainer
      center={position ?? DEFAULT_CENTER}
      zoom={DEFAULT_ZOOM}
      style={{ width: '100%', height }}
      scrollWheelZoom
    >
      <TileLayer
        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        url='https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'
      />

      <FlyToController center={position} />
      <ClickHandler onPick={onPick} />

      {position && (
        <Marker
          position={position}
          draggable
          eventHandlers={{
            dragend(e) {
              const { lat, lng } = (e.target as L.Marker).getLatLng()

              onPick(lat, lng)
            }
          }}
        />
      )}
    </MapContainer>
  )
}

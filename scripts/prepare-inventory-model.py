"""Convert a downloaded Sketchfab glTF scene to one browser-ready GLB.

Usage: blender -b --python scripts/prepare-inventory-model.py -- input.gltf output.glb
"""

import sys

import bpy
from mathutils import Vector


source, destination = sys.argv[sys.argv.index("--") + 1 :]
bpy.ops.object.select_all(action="SELECT")
bpy.ops.object.delete(use_global=False)
bpy.ops.import_scene.gltf(filepath=source)

for obj in list(bpy.data.objects):
    if obj.type in {"CAMERA", "LIGHT"} or obj.name == "Cube":
        bpy.data.objects.remove(obj, do_unlink=True)
        continue
    if obj.type != "MESH":
        continue
    points = [obj.matrix_world @ Vector(corner) for corner in obj.bound_box]
    low = [min(point[axis] for point in points) for axis in range(3)]
    high = [max(point[axis] for point in points) for axis in range(3)]
    is_imac_accessory = "imac24" in source and high[2] < 900 and high[1] < -1000
    is_display_floor = "ipadair" in source and high[2] - low[2] < 1 and max(high[0] - low[0], high[1] - low[1]) > 500
    if is_imac_accessory or is_display_floor:
        bpy.data.objects.remove(obj, do_unlink=True)

bpy.ops.export_scene.gltf(
    filepath=destination,
    export_format="GLB",
    export_image_format="AUTO",
    export_cameras=False,
    export_lights=False,
)

"use client";

import { useState } from "react";
import { AdminForm, Checkbox, DangerAction, F, inputCls, selectCls } from "@/components/admin/form";
import { deleteUser, saveUser } from "@/server/actions/admin/misc";

type U = { id: number; email: string; name: string; role: string; isActive: boolean };

export function UsersEditor({ users, meId }: { users: U[]; meId: number }) {
  const [selected, setSelected] = useState<number>(0);
  const u = users.find((x) => x.id === selected);
  return (
    <div>
      <select value={selected} onChange={(e) => setSelected(Number(e.target.value))} className={`${selectCls} mb-4 !w-auto`}>
        <option value={0}>+ Новый пользователь</option>
        {users.map((x) => <option key={x.id} value={x.id}>{x.name} ({x.email})</option>)}
      </select>
      <AdminForm key={selected} action={saveUser}>
        {u ? <input type="hidden" name="id" value={u.id} /> : null}
        <div className="grid gap-3 sm:grid-cols-2">
          <F label="Имя" name="name"><input name="name" defaultValue={u?.name ?? ""} required className={inputCls} /></F>
          <F label="E-mail" name="email"><input name="email" type="email" defaultValue={u?.email ?? ""} required className={inputCls} /></F>
          <F label="Роль" name="role"><select name="role" defaultValue={u?.role ?? "MANAGER"} className={selectCls}><option value="MANAGER">Менеджер</option><option value="ADMIN">Администратор</option></select></F>
          <F label={u ? "Новый пароль (пусто — не менять)" : "Пароль"} name="password"><input name="password" type="password" autoComplete="new-password" className={inputCls} /></F>
          <div className="sm:col-span-2"><Checkbox name="isActive" label="Активен" defaultChecked={u ? u.isActive : true} /></div>
        </div>
      </AdminForm>
      {u && u.id !== meId ? <div className="mt-3"><DangerAction action={deleteUser.bind(null, u.id)} label="Удалить пользователя" /></div> : null}
    </div>
  );
}
